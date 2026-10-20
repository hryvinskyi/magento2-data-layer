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
 * Builds `purchase` for a placed order
 *
 * Everything is read from the order and its store view's settings: no request, session or current store is needed, so
 * the event can be built in a storefront request, a payment notification, a scheduled job or the admin alike.
 */
interface PurchaseEventBuilderInterface extends OrderEventBuilderInterface
{
    /**
     * Build the event
     *
     * @param OrderInterface $order
     * @return EventInterface|null Null when the event is disabled for the order's store or the order has no item
     */
    public function build(OrderInterface $order): ?EventInterface;
}
