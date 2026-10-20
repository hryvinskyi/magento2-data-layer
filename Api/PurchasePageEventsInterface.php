<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Builds the events a success page prints to report its orders (`purchase`, or the conversion that follows it), each
 * order once per session
 *
 * When the store routes the event to the server only, the success page prints nothing: sending it from the server
 * is done for the order itself, when it is placed or paid, whatever page the shopper sees afterwards.
 */
interface PurchasePageEventsInterface
{
    /**
     * Get one event per order not yet reported in this session, and remember those orders as reported
     *
     * @param array<OrderInterface> $orders
     * @return list<EventInterface>
     */
    public function forOrders(array $orders): array;
}
