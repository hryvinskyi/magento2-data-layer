<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\ServerEventDrainInterface;
use Hryvinskyi\DataLayer\Api\ServerEventSignalInterface;
use Hryvinskyi\DataLayer\Api\Session\VisitorSessionInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\ServerEventQueueInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

/**
 * Keeps the events detected on the server in the visitor's session until the browser has pushed them
 *
 * Each event is kept as the plain object pushed to the data layer (arrays and scalars, never objects), so the session
 * holds no class the next request would have to load, with a random id of its own and the time it was queued. The
 * queue holds at most fifty events: a visitor whose browser never takes them (scripts blocked, a page without the data
 * layer) cannot grow the session without bound. When it is full, the oldest event is dropped and the drop is logged.
 * An event older than thirty minutes is dropped when the queue is next read: the shopper has moved on, and pushing it
 * on a much later page would report it out of its context. An entry without an id or a queue time is not an event of
 * this queue and is dropped too.
 *
 * Reading the waiting events leaves them queued; the browser acknowledges the ids it pushed, and only those are
 * removed. That protects only within one request's session data: an event this request queues after the browser read
 * the queue stays for the next read. It does not protect against another request, which works on its own copy of the
 * session data. Safety across requests rests entirely on Magento's session locking, which runs a visitor's requests
 * one after another; where the session storage does not lock, two requests that save the session at once are saved
 * last-wins, and an event queued by the one saved first can be lost, or an acknowledged one handed over again (the
 * browser skips an id it already pushed).
 *
 * Queuing an event tells the browser that events wait; an acknowledgement that leaves the queue empty, or a read that
 * finds it empty, tells it that nothing waits any more. The signal decides on which responses it can travel.
 */
class ServerEventQueue implements ServerEventQueueInterface, ServerEventDrainInterface
{
    public const CAPACITY = 50;

    /**
     * How long an event may wait for the browser, in seconds
     */
    public const MAX_AGE_SECONDS = 1800;

    private const KEY = 'server_event_queue';
    private const ID_BYTES = 8;

    /**
     * @param VisitorSessionInterface $session
     * @param ServerEventSignalInterface $signal
     * @param LoggerInterface $logger
     * @param DateTime $dateTime Tells the current time
     */
    public function __construct(
        private readonly VisitorSessionInterface $session,
        private readonly ServerEventSignalInterface $signal,
        private readonly LoggerInterface $logger,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @inheritDoc
     */
    public function add(EventInterface $event): void
    {
        $now = $this->now();
        [$entries] = $this->read($now);
        $entries[] = [
            'id' => bin2hex(random_bytes(self::ID_BYTES)),
            'queued_at' => $now,
            'event' => $event->toDataLayer(),
        ];
        while (count($entries) > self::CAPACITY) {
            $dropped = array_shift($entries);
            $this->logger->warning('A queued data layer event was dropped: the visitor\'s queue is full.', [
                'event' => $dropped['event']['event'] ?? null,
                'capacity' => self::CAPACITY,
            ]);
        }

        $this->write($entries);
        $this->signal->signal();
    }

    /**
     * @inheritDoc
     */
    public function pending(): array
    {
        [$entries, $stored] = $this->read($this->now());
        if (count($entries) !== $stored) {
            $this->write($entries);
        }
        if ($entries === []) {
            $this->signal->clear();
        }

        return array_map(
            static fn (array $entry): array => ['id' => $entry['id'], 'event' => $entry['event']],
            $entries
        );
    }

    /**
     * @inheritDoc
     */
    public function acknowledge(array $ids): void
    {
        $acknowledged = array_flip($ids);
        [$entries, $stored] = $this->read($this->now());
        $left = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => !isset($acknowledged[$entry['id']])
        ));
        if (count($left) !== $stored) {
            $this->write($left);
        }

        if ($left === []) {
            $this->signal->clear();
        } else {
            $this->signal->signal();
        }
    }

    /**
     * @inheritDoc
     */
    public function hasEvents(): bool
    {
        return $this->read($this->now())[0] !== [];
    }

    /**
     * Read the queue once
     *
     * Gives the queued events that have not expired, oldest first, and how many entries the session holds under the
     * queue's key, events or not.
     *
     * @param int $now The current time, in seconds since the epoch
     * @return array{0: list<array{id: string, queued_at: int, event: array<string, mixed>}>, 1: int}
     */
    private function read(int $now): array
    {
        $stored = $this->session->get(self::KEY);
        if (!is_array($stored)) {
            return [[], 0];
        }

        $entries = [];
        foreach ($stored as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $id = $entry['id'] ?? null;
            $queuedAt = $entry['queued_at'] ?? null;
            $event = $entry['event'] ?? null;
            if (!is_string($id) || $id === '' || !is_int($queuedAt) || !is_array($event)
                || !is_string($event['event'] ?? null)
                || $now - $queuedAt > self::MAX_AGE_SECONDS
            ) {
                continue;
            }
            $entries[] = ['id' => $id, 'queued_at' => $queuedAt, 'event' => $this->keyedByName($event)];
        }

        return [$entries, count($stored)];
    }

    /**
     * Store the entries, or remove the queue from the session when there are none
     *
     * @param list<array<string,mixed>> $entries
     * @return void
     */
    private function write(array $entries): void
    {
        if ($entries === []) {
            $this->session->remove(self::KEY);

            return;
        }

        $this->session->set(self::KEY, $entries);
    }

    /**
     * Get the current time
     *
     * @return int Seconds since the epoch
     */
    private function now(): int
    {
        return $this->dateTime->gmtTimestamp();
    }

    /**
     * Keep the entries of a stored event object whose keys are names
     *
     * @param array<mixed> $event
     * @return array<string, mixed>
     */
    private function keyedByName(array $event): array
    {
        $named = [];
        foreach ($event as $key => $value) {
            if (is_string($key)) {
                $named[$key] = $value;
            }
        }

        return $named;
    }
}
