<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Event;

use Hryvinskyi\DataLayerApi\Api\Data\ItemInterface;

/**
 * Adds up the money a set of reported items stands for: the `value` of every event about a cart, a cart line or a
 * catalogue product (a checkout step's too, unless its store view values checkout steps at the cart's grand total)
 *
 * The sum is taken from the items as they are reported, so `value` is on the same tax basis as their prices and
 * agrees with them: each item counts its `price` less its `discount` (both per unit), times its `quantity`.
 */
interface ItemsValueInterface
{
    /**
     * Get the value of the items
     *
     * @param list<ItemInterface> $items
     * @return float Σ (price − discount) × quantity, rounded to 2 decimals and never below zero; an item without a
     *     price counts zero
     */
    public function value(array $items): float;
}
