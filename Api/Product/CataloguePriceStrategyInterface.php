<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Product;

use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Finds the price a catalogue listing shows for products of one product type
 *
 * Registered per product type id in the `strategies` argument of the catalogue price resolver, with a `default`
 * entry for every type that has no strategy of its own. A strategy knows which product its price belongs to, and so
 * whose tax class applies when the price is wanted including tax.
 */
interface CataloguePriceStrategyInterface
{
    /**
     * Get the unit price the listing shows for the product, on the requested tax basis
     *
     * @param ProductInterface $product
     * @param bool $includingTax
     * @param float $quantity The quantity the unit price is for: one for a listing, more for a tier price
     * @return float|null Null when the product has no price to show
     */
    public function price(ProductInterface $product, bool $includingTax, float $quantity): ?float;
}
