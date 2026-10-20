<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Product\Price;

use Hryvinskyi\DataLayer\Api\Product\CataloguePriceStrategyInterface;
use Hryvinskyi\DataLayer\Api\Product\QuantityPriceInfoInterface;
use Hryvinskyi\DataLayer\Api\Product\TaxBasisPriceInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Pricing\SaleableInterface;

/**
 * The product's own final price for the quantity, as the listing shows it
 */
class FinalPriceAmount implements CataloguePriceStrategyInterface
{
    /**
     * @param QuantityPriceInfoInterface $quantityPricing
     * @param TaxBasisPriceInterface $taxBasis
     */
    public function __construct(
        private readonly QuantityPriceInfoInterface $quantityPricing,
        private readonly TaxBasisPriceInterface $taxBasis
    ) {
    }

    /**
     * @inheritDoc
     */
    public function price(ProductInterface $product, bool $includingTax, float $quantity): ?float
    {
        if (!$product instanceof SaleableInterface) {
            return null;
        }

        $amount = $this->quantityPricing->forQuantity($product, $quantity)
            ->getPrice(FinalPrice::PRICE_CODE)
            ->getAmount();

        return $this->taxBasis->price($amount, $product, $includingTax);
    }
}
