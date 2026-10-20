<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\PageEvent;

use Hryvinskyi\DataLayer\Api\Event\OrderEventBuilderInterface;
use Hryvinskyi\DataLayer\Api\PurchaseLedgerInterface;
use Hryvinskyi\DataLayer\Api\PurchasePageEventsInterface;
use Hryvinskyi\DataLayerApi\Api\EventChannelPolicyInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Prints one event per order on a success page, once per order per session, unless the store sends it from the server
 * only
 *
 * By default the event is `purchase`; the same rules print the conversion that follows it, with that event's builder,
 * name and a ledger of its own (`ads_conversion`), so a reload reports neither twice and one never marks the other.
 * An order is remembered as reported only when an event was built for it, so an order whose event was disabled at the
 * time is not silently marked.
 */
class PurchasePageEvents implements PurchasePageEventsInterface
{
    /**
     * @param OrderEventBuilderInterface $purchaseBuilder Builds the event for one order
     * @param PurchaseLedgerInterface $ledger Remembers the orders this event was reported for
     * @param EventChannelPolicyInterface $channels
     * @param string $eventName The name of the event the builder builds
     */
    public function __construct(
        private readonly OrderEventBuilderInterface $purchaseBuilder,
        private readonly PurchaseLedgerInterface $ledger,
        private readonly EventChannelPolicyInterface $channels,
        private readonly string $eventName = 'purchase'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function forOrders(array $orders): array
    {
        $events = [];
        foreach ($orders as $order) {
            $incrementId = trim((string) $order->getIncrementId());
            if ($incrementId === '' || !$this->printsOnPage($order) || $this->ledger->wasReported($incrementId)) {
                continue;
            }
            $event = $this->purchaseBuilder->build($order);
            if ($event === null) {
                continue;
            }
            $this->ledger->markReported($incrementId);
            $events[] = $event;
        }

        return $events;
    }

    /**
     * Whether the order's store delivers the event through the browser
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function printsOnPage(OrderInterface $order): bool
    {
        return $this->channels->channelOf($this->eventName, (int) $order->getStoreId())->includesBrowser();
    }
}
