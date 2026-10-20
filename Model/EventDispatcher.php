<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\BrowserEventForwarderInterface;
use Hryvinskyi\DataLayer\Api\EventModifierPipelineInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\VisitorContextInterface;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Hryvinskyi\DataLayerApi\Api\EventChannelPolicyInterface;
use Hryvinskyi\DataLayerApi\Api\EventDispatcherInterface;
use Hryvinskyi\DataLayerApi\Api\ServerEventQueueInterface;
use Hryvinskyi\DataLayerApi\Api\ServerEventSinkInterface;
use Hryvinskyi\DataLayerApi\Api\VisitorContextProviderInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Routes events built on the server to the visitor's browser, to the server sinks, or both
 *
 * A disabled event is dropped before anything else; the modifiers run next, and the store view's channel for the
 * event decides where it goes. The browser queue is used only inside a storefront request (the storefront or its REST
 * API), since no other request has a visitor's browser to deliver to. Sinks are registered through the `sinks`
 * argument; each runs on its own, so one that fails does not keep the event from the others. Nothing thrown while
 * dispatching leaves the dispatcher: it is called from inside order placement, where an exception would fail a
 * checkout whose order already exists. Every failure is logged.
 *
 * An event a visitor's browser already reported is forwarded to the sinks without the modifiers: they ran when its
 * page was printed.
 */
class EventDispatcher implements EventDispatcherInterface, BrowserEventForwarderInterface
{
    private const STOREFRONT_AREAS = [Area::AREA_FRONTEND, Area::AREA_WEBAPI_REST];

    /**
     * @var list<ServerEventSinkInterface>
     */
    private readonly array $sinks;

    /**
     * @param EventAvailabilityInterface $availability
     * @param EventModifierPipelineInterface $modifiers
     * @param EventChannelPolicyInterface $channels
     * @param ServerEventQueueInterface $queue
     * @param VisitorContextProviderInterface $visitors
     * @param State $appState
     * @param LoggerInterface $logger
     * @param array<mixed> $sinks Each a ServerEventSinkInterface
     * @throws \InvalidArgumentException When a sink is not a ServerEventSinkInterface
     */
    public function __construct(
        private readonly EventAvailabilityInterface $availability,
        private readonly EventModifierPipelineInterface $modifiers,
        private readonly EventChannelPolicyInterface $channels,
        private readonly ServerEventQueueInterface $queue,
        private readonly VisitorContextProviderInterface $visitors,
        private readonly State $appState,
        private readonly LoggerInterface $logger,
        array $sinks = []
    ) {
        $accepted = [];
        foreach ($sinks as $name => $sink) {
            if (!$sink instanceof ServerEventSinkInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'The server event sink "%s" must implement %s.',
                    (string) $name,
                    ServerEventSinkInterface::class
                ));
            }
            $accepted[] = $sink;
        }
        $this->sinks = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function dispatch(EventInterface $event, int $storeId, ?VisitorContextInterface $visitor = null): void
    {
        try {
            $event = $this->prepare($event, $storeId);
            if ($event === null) {
                return;
            }
            $channel = $this->channels->channelOf($event->getName(), $storeId);
        } catch (\Throwable $exception) {
            $this->logFailure('could not be routed', $event, $storeId, $exception);
            return;
        }

        if ($channel->includesBrowser() && $this->isStorefrontRequest()) {
            $this->queueForBrowser($event, $storeId);
        }
        if ($channel->includesServer()) {
            $this->sendToSinks($event, $storeId, $visitor, null);
        }
    }

    /**
     * @inheritDoc
     */
    public function dispatchToServer(
        EventInterface $event,
        int $storeId,
        ?VisitorContextInterface $visitor = null,
        ?string $deduplicationKey = null
    ): void {
        try {
            $event = $this->prepare($event, $storeId);
            if ($event === null || !$this->channels->channelOf($event->getName(), $storeId)->includesServer()) {
                return;
            }
        } catch (\Throwable $exception) {
            $this->logFailure('could not be routed', $event, $storeId, $exception);
            return;
        }

        $this->sendToSinks($event, $storeId, $visitor, $deduplicationKey);
    }

    /**
     * @inheritDoc
     */
    public function forward(
        EventInterface $event,
        int $storeId,
        ?VisitorContextInterface $visitor = null,
        ?string $deduplicationKey = null
    ): void {
        try {
            if (!$this->availability->isEnabled($event->getName(), $storeId)
                || !$this->channels->channelOf($event->getName(), $storeId)->includesServer()
            ) {
                return;
            }
        } catch (\Throwable $exception) {
            $this->logFailure('could not be forwarded', $event, $storeId, $exception);
            return;
        }

        $this->sendToSinks($event, $storeId, $visitor, $deduplicationKey);
    }

    /**
     * Drop a disabled event, then run the modifiers
     *
     * @param EventInterface $event
     * @param int $storeId
     * @return EventInterface|null Null when the event is disabled or a modifier dropped it
     */
    private function prepare(EventInterface $event, int $storeId): ?EventInterface
    {
        if (!$this->availability->isEnabled($event->getName(), $storeId)) {
            return null;
        }

        return $this->modifiers->apply($event);
    }

    /**
     * Queue the event for the visitor's browser
     *
     * @param EventInterface $event
     * @param int $storeId
     * @return void
     */
    private function queueForBrowser(EventInterface $event, int $storeId): void
    {
        try {
            $this->queue->add($event);
        } catch (\Throwable $exception) {
            $this->logFailure('could not be queued for the browser', $event, $storeId, $exception);
        }
    }

    /**
     * Hand the event to every sink, each on its own
     *
     * @param EventInterface $event
     * @param int $storeId
     * @param VisitorContextInterface|null $visitor Null for the current request's visitor
     * @param string|null $deduplicationKey Passed to every sink as given
     * @return void
     */
    private function sendToSinks(
        EventInterface $event,
        int $storeId,
        ?VisitorContextInterface $visitor,
        ?string $deduplicationKey
    ): void {
        if ($this->sinks === []) {
            return;
        }

        $visitor ??= $this->currentVisitor($event, $storeId);
        foreach ($this->sinks as $sink) {
            try {
                $sink->accept($event, $storeId, $visitor, $deduplicationKey);
            } catch (\Throwable $exception) {
                $this->logFailure(
                    sprintf('was refused by the server sink %s', get_class($sink)),
                    $event,
                    $storeId,
                    $exception
                );
            }
        }
    }

    /**
     * Get the current request's visitor; a provider that fails leaves the visitor unknown
     *
     * @param EventInterface $event
     * @param int $storeId
     * @return VisitorContextInterface|null
     */
    private function currentVisitor(EventInterface $event, int $storeId): ?VisitorContextInterface
    {
        try {
            return $this->visitors->current();
        } catch (\Throwable $exception) {
            $this->logFailure('is sent without a visitor: reading the visitor failed', $event, $storeId, $exception);
            return null;
        }
    }

    /**
     * Whether the current request comes from the storefront or its REST API
     *
     * @return bool
     */
    private function isStorefrontRequest(): bool
    {
        try {
            return in_array($this->appState->getAreaCode(), self::STOREFRONT_AREAS, true);
        } catch (LocalizedException) {
            return false;
        }
    }

    /**
     * Log a dispatch failure with the event and store it concerns
     *
     * @param string $what What happened to the event
     * @param EventInterface $event
     * @param int $storeId
     * @param \Throwable $exception
     * @return void
     */
    private function logFailure(string $what, EventInterface $event, int $storeId, \Throwable $exception): void
    {
        $this->logger->error(
            sprintf('Data layer event "%s" %s: %s', $event->getName(), $what, $exception->getMessage()),
            ['store_id' => $storeId, 'exception' => $exception]
        );
    }
}
