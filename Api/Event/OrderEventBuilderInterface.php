<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Event;

use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Builds one event that reports a placed order, from the order and its store view's settings alone
 *
 * No request, session or current store is needed, so the event can be built in any request that has the order.
 */
interface OrderEventBuilderInterface
{
    /**
     * Build the event
     *
     * @param OrderInterface $order
     * @return EventInterface|null Null when the order's store view reports no such event for it
     */
    public function build(OrderInterface $order): ?EventInterface;
}
