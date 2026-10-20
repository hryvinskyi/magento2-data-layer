<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Event;

use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Magento\Quote\Api\Data\CartItemInterface;

/**
 * Builds the event of a quantity change of one cart line (a product added to or removed from the cart)
 */
interface CartLineEventBuilderInterface
{
    /**
     * Build the event for a cart line whose quantity changed by the given amount
     *
     * @param CartItemInterface $line The cart line (the parent line of a configurable or bundle product)
     * @param float $quantity How many units were added or removed, always positive
     * @return EventInterface|null Null when the event is disabled, the cart is not tracked, the line yields no item or
     *     the hidden-price setting skips the event
     */
    public function build(CartItemInterface $line, float $quantity): ?EventInterface;
}
