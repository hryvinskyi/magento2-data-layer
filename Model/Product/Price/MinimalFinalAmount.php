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
use Magento\Catalog\Pricing\Price\FinalPriceInterface;
use Magento\Framework\Pricing\SaleableInterface;

/**
 * The lowest final price the product can be bought for, as a listing's "from" price shows it
 *
 * For a product put together from selections, the cheapest combination the shopper can choose. Its tax, when wanted
 * and not computed by the catalogue, follows the product's own tax class.
 */
class MinimalFinalAmount implements CataloguePriceStrategyInterface
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

        $finalPrice = $this->quantityPricing->forQuantity($product, $quantity)->getPrice(FinalPrice::PRICE_CODE);
        $amount = $finalPrice instanceof FinalPriceInterface
            ? $finalPrice->getMinimalPrice()
            : $finalPrice->getAmount();

        return $this->taxBasis->price($amount, $product, $includingTax);
    }
}
