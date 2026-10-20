<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Product;

use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * Describes the options a shopper chose on a cart or order line of one product type
 *
 * Registered per product type id in the `strategies` argument of the line variant resolver, with a `default`
 * entry for every type that has no strategy of its own.
 */
interface LineVariantStrategyInterface
{
    /**
     * Get the line's chosen options as text
     *
     * @param CartItemInterface|OrderItemInterface $line
     * @return string|null `Label: Value, Label: Value`; null when nothing was chosen
     */
    public function variant(CartItemInterface|OrderItemInterface $line): ?string;
}
