<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Event;

use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Builds the event of a checkout step the shopper completed for a cart (shipping information, payment information)
 */
interface CheckoutStepEventBuilderInterface
{
    /**
     * Build the event for the cart whose step was completed
     *
     * @param CartInterface $cart
     * @return EventInterface|null Null when the event is disabled, the cart is not tracked or has no item, or the
     *     hidden-price setting skips the event
     */
    public function build(CartInterface $cart): ?EventInterface;
}
