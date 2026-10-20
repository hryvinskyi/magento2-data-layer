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
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;

/**
 * The lowest final price among a grouped product's associated products, as the listing's "starting at" price shows it
 *
 * The price is the associated product's own, so its tax follows that product's tax class: a grouped product carries
 * no tax class of its own.
 */
class LowestAssociatedAmount implements CataloguePriceStrategyInterface
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
        if (!$product instanceof Product) {
            return null;
        }
        $type = $product->getTypeInstance();
        if (!$type instanceof Grouped) {
            return null;
        }

        $lowest = null;
        $lowestProduct = null;
        foreach ($type->getAssociatedProducts($product) as $associated) {
            if (!$associated instanceof Product) {
                continue;
            }
            $amount = $this->amount($associated, $quantity);
            if ($lowest === null || $amount->getBaseAmount() < $lowest->getBaseAmount()) {
                $lowest = $amount;
                $lowestProduct = $associated;
            }
        }

        return $lowest !== null && $lowestProduct !== null
            ? $this->taxBasis->price($lowest, $lowestProduct, $includingTax)
            : null;
    }

    /**
     * Get an associated product's final price amount for the quantity
     *
     * @param Product $associated
     * @param float $quantity
     * @return AmountInterface
     */
    private function amount(Product $associated, float $quantity): AmountInterface
    {
        return $this->quantityPricing->forQuantity($associated, $quantity)
            ->getPrice(FinalPrice::PRICE_CODE)
            ->getAmount();
    }
}
