<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Product\Price;

use Hryvinskyi\DataLayer\Api\Product\QuantityPriceInfoInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Pricing\PriceInfo\Factory as PriceInfoFactory;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Framework\Pricing\SaleableInterface;

/**
 * Reuses the product's own pricing for one unit and builds pricing of its own for any other quantity
 *
 * The product keeps its pricing for one unit (the listing's), so a quantity's pricing is built apart and never
 * replaces it. Magento's pricing factory takes the quantity from the product's own `qty`, whatever quantity it is
 * asked for, so the pricing is built for a copy of the product whose `qty` is the requested quantity: the product
 * itself, which other code shares, is never changed. A product that holds no data of its own cannot be copied that
 * way and is priced for the quantity it carries.
 */
class QuantityPriceInfo implements QuantityPriceInfoInterface
{
    private const ONE = 1.0;
    private const NOISE = 0.0001;

    /**
     * @param PriceInfoFactory $priceInfoFactory
     */
    public function __construct(private readonly PriceInfoFactory $priceInfoFactory)
    {
    }

    /**
     * @inheritDoc
     */
    public function forQuantity(SaleableInterface $product, float $quantity): PriceInfoInterface
    {
        if (abs($quantity - self::ONE) < self::NOISE || $quantity <= 0.0) {
            return $product->getPriceInfo();
        }

        if (!$product instanceof DataObject) {
            return $this->priceInfoFactory->create($product, ['quantity' => $quantity]);
        }

        $copy = clone $product;
        $copy->setData('qty', $quantity);

        return $this->priceInfoFactory->create($copy, ['quantity' => $quantity]);
    }
}
