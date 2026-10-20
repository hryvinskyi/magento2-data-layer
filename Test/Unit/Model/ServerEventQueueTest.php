<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Model\Data\Item;
use Hryvinskyi\DataLayer\Model\ServerEventQueue;
use Hryvinskyi\DataLayer\Model\Session\VisitorSession;
use Hryvinskyi\DataLayer\Test\Unit\Model\Session\FakeVisitorSession;
use Magento\Framework\Session\Generic;
use Magento\Framework\Session\Storage;
use PHPUnit\Framework\TestCase;

/**
 * The session queue keeps plain event objects with ids, holds at most fifty (dropping and logging the oldest), hands
 * them over until the browser acknowledges them, drops what waited too long, and tells the browser when events wait
 * and when nothing does
 */
class ServerEventQueueTest extends TestCase
{
    /**
     * @var FakeVisitorSession
     */
    private FakeVisitorSession $session;

    /**
     * @var RecordingLogger
     */
    private RecordingLogger $logger;

    /**
     * @var RecordingSignal
     */
    private RecordingSignal $signal;

    /**
     * @var FixedDateTime
     */
    private FixedDateTime $clock;

    /**
     * @var ServerEventQueue
     */
    private ServerEventQueue $queue;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->session = new FakeVisitorSession();
        $this->logger = new RecordingLogger();
        $this->signal = new RecordingSignal();
        $this->clock = new FixedDateTime();
        $this->queue = new ServerEventQueue($this->session, $this->signal, $this->logger, $this->clock);
    }

    /**
     * Events are kept as the objects pushed to the data layer, oldest first, each with an id of its own; reading
     * them leaves them queued, so a second read returns the same events with the same ids
     *
     * @return void
     */
    public function testReadingLeavesTheEventsQueued(): void
    {
        $this->queue->add(new Event('add_to_cart', true, ['currency' => 'EUR'], [new Item(['item_id' => 'A'])]));
        $this->queue->add(new Event('login', false, ['method' => 'website']));

        $first = $this->queue->pending();
        self::assertSame(
            [
                ['event' => 'add_to_cart', 'ecommerce' => ['currency' => 'EUR', 'items' => [['item_id' => 'A']]]],
                ['event' => 'login', 'method' => 'website'],
            ],
            array_column($first, 'event')
        );
        self::assertCount(2, array_unique(array_column($first, 'id')), 'each event has an id of its own');
        self::assertSame($first, $this->queue->pending());
        self::assertTrue($this->queue->hasEvents());
    }

    /**
     * Acknowledging ids removes exactly those events; acknowledging them again, or ids the queue never held, changes
     * nothing
     *
     * @return void
     */
    public function testAcknowledgedEventsAreRemovedOnce(): void
    {
        $this->queue->add(new Event('login', false, ['number' => 1]));
        $this->queue->add(new Event('sign_up', false, ['number' => 2]));
        [$login, $signUp] = $this->queue->pending();

        $this->queue->acknowledge([$login['id'], 'unknown-id']);
        self::assertSame([$signUp], $this->queue->pending());

        $this->queue->acknowledge([$login['id']]);
        self::assertSame([$signUp], $this->queue->pending());

        $this->queue->acknowledge([$signUp['id']]);
        self::assertSame([], $this->queue->pending());
        self::assertFalse($this->queue->hasEvents());
        self::assertSame([], $this->session->values, 'An empty queue leaves nothing in the session.');
    }

    /**
     * An event queued after the browser read the queue survives the acknowledgement of what it read, and keeps the
     * signal set
     *
     * @return void
     */
    public function testEventQueuedAfterTheReadSurvivesTheAcknowledgement(): void
    {
        $this->queue->add(new Event('login', false, ['number' => 1]));
        $read = $this->queue->pending();
        $this->queue->add(new Event('sign_up', false, ['number' => 2]));
        $this->signal->calls = [];

        $this->queue->acknowledge(array_column($read, 'id'));

        self::assertSame(['signal'], $this->signal->calls);
        self::assertSame([['event' => 'sign_up', 'number' => 2]], array_column($this->queue->pending(), 'event'));
    }

    /**
     * The queue holds the fifty most recent events and logs every one it drops
     *
     * @return void
     */
    public function testQueueIsBounded(): void
    {
        for ($i = 1; $i <= ServerEventQueue::CAPACITY + 2; $i++) {
            $this->queue->add(new Event('login', false, ['number' => $i]));
        }

        $events = array_column($this->queue->pending(), 'event');
        self::assertCount(ServerEventQueue::CAPACITY, $events);
        self::assertSame(3, $events[0]['number']);
        self::assertSame(ServerEventQueue::CAPACITY + 2, $events[ServerEventQueue::CAPACITY - 1]['number']);
        self::assertCount(2, $this->logger->records);
        self::assertSame('login', $this->logger->records[0]['context']['event']);
    }

    /**
     * An event that waited longer than the age limit is never handed over, and is removed from the session; one
     * that waited exactly the limit still is
     *
     * @return void
     */
    public function testStaleEventsExpire(): void
    {
        $this->queue->add(new Event('login', false, ['number' => 1]));
        $this->clock->now += 600;
        $this->queue->add(new Event('sign_up', false, ['number' => 2]));

        $this->clock->now += ServerEventQueue::MAX_AGE_SECONDS - 600;
        self::assertSame(['login', 'sign_up'], array_column(array_column($this->queue->pending(), 'event'), 'event'));

        $this->clock->now += 1;
        self::assertSame(['sign_up'], array_column(array_column($this->queue->pending(), 'event'), 'event'));
        $stored = $this->storedQueue();
        self::assertIsArray($stored);
        self::assertCount(1, $stored);

        $this->clock->now += 600;
        self::assertFalse($this->queue->hasEvents());
        self::assertSame([], $this->queue->pending());
        self::assertSame([], $this->session->values);
    }

    /**
     * Whatever else the session holds under the queue's key is ignored and cleaned away on the next read, entries
     * without an id or a queue time included
     *
     * @return void
     */
    public function testJunkInTheSessionIsIgnored(): void
    {
        $now = $this->clock->now;
        $this->session->values['server_event_queue'] = [
            'nonsense',
            ['no' => 'name'],
            ['event' => 'login', 'method' => 'website'],
            ['id' => 'a', 'event' => ['event' => 'login']],
            ['id' => '', 'queued_at' => $now, 'event' => ['event' => 'login']],
            ['id' => 'b', 'queued_at' => $now, 'event' => ['no' => 'name']],
            ['id' => 'c', 'queued_at' => $now, 'event' => ['event' => 'login', 7 => 'x']],
        ];

        self::assertTrue($this->queue->hasEvents());
        self::assertSame([['id' => 'c', 'event' => ['event' => 'login']]], $this->queue->pending());
        $stored = $this->storedQueue();
        self::assertIsArray($stored);
        self::assertCount(1, $stored);
    }

    /**
     * Junk under the queue's key is not a waiting event
     *
     * @return void
     */
    public function testJunkIsNotAWaitingEvent(): void
    {
        $this->session->values['server_event_queue'] = ['nonsense', ['no' => 'name']];

        self::assertFalse($this->queue->hasEvents());
    }

    /**
     * Every queued event signals the browser; a read signals nothing while events wait and clears the signal when
     * none do (a stale signal is cleared); an acknowledgement clears it once nothing is left; asking whether events
     * wait changes nothing
     *
     * @return void
     */
    public function testSignalFollowsTheQueue(): void
    {
        self::assertFalse($this->queue->hasEvents());
        $this->queue->add(new Event('login', false));
        $this->queue->add(new Event('sign_up', false));
        self::assertSame(['signal', 'signal'], $this->signal->calls);

        self::assertTrue($this->queue->hasEvents());
        $read = $this->queue->pending();
        self::assertSame(['signal', 'signal'], $this->signal->calls);

        $this->queue->acknowledge([$read[0]['id']]);
        self::assertSame(['signal', 'signal', 'signal'], $this->signal->calls);
        $this->queue->acknowledge([$read[1]['id']]);
        self::assertSame(['signal', 'signal', 'signal', 'clear'], $this->signal->calls);

        self::assertSame([], $this->queue->pending());
        self::assertSame(['signal', 'signal', 'signal', 'clear', 'clear'], $this->signal->calls);
    }

    /**
     * The session adapter reads, writes and removes values in its namespace
     *
     * @return void
     */
    public function testSessionAdapterUsesItsNamespace(): void
    {
        $namespace = new class extends Generic {
            /**
             * Keep the namespace's data in memory, without starting a PHP session
             */
            public function __construct()
            {
                $this->storage = new Storage('test');
            }
        };
        $session = new VisitorSession($namespace);

        $session->set('queue', [['event' => 'login']]);
        self::assertSame([['event' => 'login']], $session->get('queue'));
        $session->remove('queue');
        self::assertNull($session->get('queue'));
    }

    /**
     * Read what the session holds under the queue's key, as the next request would
     *
     * @return mixed
     */
    private function storedQueue(): mixed
    {
        return $this->session->get('server_event_queue');
    }
}
