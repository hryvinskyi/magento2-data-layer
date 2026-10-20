<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Event;

use Hryvinskyi\DataLayerApi\Api\Data\ItemInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Tells what an event about a whole cart is worth (its `value`), one way of valuing it per implementation
 *
 * The value is in the cart's currency, the one the event reports.
 */
interface CartValueInterface
{
    /**
     * Get the value of an event about the cart
     *
     * @param CartInterface $cart
     * @param list<ItemInterface> $items The event's items, as reported
     * @return float Rounded to 2 decimals
     */
    public function valueOf(CartInterface $cart, array $items): float;
}
