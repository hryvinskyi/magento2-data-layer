<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Product\Price;

use Hryvinskyi\DataLayer\Api\Product\TaxBasisPriceInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Tax\Api\TaxCalculationInterface;

/**
 * Takes the tax the catalogue computed for an amount, or calculates it at the rate of the priced product's tax class
 *
 * The catalogue computes the tax adjustment when catalogue prices include tax or the store shows prices including
 * tax (or both). Otherwise the amount carries no tax and its amount before adjustments is a price excluding tax:
 * Magento's tax calculation gives the rate of the priced product's tax class for the store's default tax destination
 * and default customer tax class, and the price including tax is that price plus the rate. A product without a tax
 * class pays no tax.
 */
class TaxBasisPrice implements TaxBasisPriceInterface
{
    private const TAX_ADJUSTMENT = 'tax';

    /**
     * @param TaxCalculationInterface $taxCalculation
     */
    public function __construct(private readonly TaxCalculationInterface $taxCalculation)
    {
    }

    /**
     * @inheritDoc
     */
    public function price(AmountInterface $amount, SaleableInterface $pricedItem, bool $includingTax): float
    {
        $price = (float) $amount->getBaseAmount();
        if (!$includingTax) {
            return $price;
        }
        if ($amount->hasAdjustment(self::TAX_ADJUSTMENT)) {
            return $price + (float) $amount->getAdjustmentAmount(self::TAX_ADJUSTMENT);
        }

        $taxClassId = $this->number($pricedItem instanceof DataObject ? $pricedItem->getData('tax_class_id') : null);
        if ($taxClassId === null || $taxClassId <= 0) {
            return $price;
        }
        $storeId = $this->number($pricedItem instanceof DataObject ? $pricedItem->getData('store_id') : null);
        $rate = (float) $this->taxCalculation->getCalculatedRate(
            $taxClassId,
            null,
            $storeId === null ? null : (string) $storeId
        );

        return $price * (1 + $rate / 100);
    }

    /**
     * Read a stored id
     *
     * @param mixed $value
     * @return int|null Null when nothing numeric is stored
     */
    private function number(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
