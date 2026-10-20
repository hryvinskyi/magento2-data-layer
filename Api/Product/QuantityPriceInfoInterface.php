<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Product;

use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Framework\Pricing\SaleableInterface;

/**
 * Gives the catalogue pricing of a product for a quantity, so a tier price for that quantity applies
 */
interface QuantityPriceInfoInterface
{
    /**
     * Get the product's pricing for the quantity
     *
     * @param SaleableInterface $product
     * @param float $quantity One for a listing; a cart line's quantity for its tier price
     * @return PriceInfoInterface
     */
    public function forQuantity(SaleableInterface $product, float $quantity): PriceInfoInterface;
}
