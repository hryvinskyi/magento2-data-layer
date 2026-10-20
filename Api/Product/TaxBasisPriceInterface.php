<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Product;

use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\SaleableInterface;

/**
 * Reads a catalogue amount on the requested tax basis, the same basis a cart line's unit price is on
 *
 * Excluding tax, the price is the amount before any adjustment. Including tax, it is that amount plus the tax the
 * catalogue computed for it; when the store shows prices excluding tax the catalogue computes none, and the tax is
 * then calculated by Magento's tax calculation at the rate of the priced product's tax class for the store's default
 * tax destination and default customer tax class.
 */
interface TaxBasisPriceInterface
{
    /**
     * Get an amount's price on the requested tax basis
     *
     * @param AmountInterface $amount The amount a listing shows
     * @param SaleableInterface $pricedItem The product the amount is the price of, whose tax class applies
     * @param bool $includingTax
     * @return float
     */
    public function price(AmountInterface $amount, SaleableInterface $pricedItem, bool $includingTax): float;
}
