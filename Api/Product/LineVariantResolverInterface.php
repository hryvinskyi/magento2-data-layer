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
 * Gets a cart or order line's GA4 `item_variant`, by the line's product type
 */
interface LineVariantResolverInterface
{
    /**
     * Get the line's chosen options as text
     *
     * @param CartItemInterface|OrderItemInterface $line
     * @return string|null `Label: Value, Label: Value`; null when nothing was chosen
     */
    public function variant(CartItemInterface|OrderItemInterface $line): ?string;
}
