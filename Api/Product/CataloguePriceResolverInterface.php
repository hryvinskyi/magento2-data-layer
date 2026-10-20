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
 * Gets the unit price a catalogue item reports, by the product's type
 */
interface CataloguePriceResolverInterface
{
    /**
     * Get the product's catalogue unit price
     *
     * Excluding tax, the price is the shown amount before any adjustment; including tax, it is that amount plus its
     * tax, calculated for the product's tax class even when the store shows prices excluding tax. Either way it is on
     * the same basis as a cart line's unit price.
     *
     * @param ProductInterface $product
     * @param bool $includingTax
     * @param float $quantity The quantity the unit price is for: one for a listing; a cart line's quantity, so the
     *     tier price for that quantity applies
     * @return float|null Null when the product has no price to show
     */
    public function unitPrice(ProductInterface $product, bool $includingTax, float $quantity = 1.0): ?float;
}
