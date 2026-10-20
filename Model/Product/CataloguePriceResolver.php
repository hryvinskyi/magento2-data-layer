<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Product;

use Hryvinskyi\DataLayer\Api\Product\CataloguePriceResolverInterface;
use Hryvinskyi\DataLayer\Api\Product\CataloguePriceStrategyInterface;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Gets a catalogue item's unit price from the strategy registered for the product's type
 *
 * Strategies are keyed by product type id; a type without a strategy of its own uses the `default` one, which must
 * be registered.
 */
class CataloguePriceResolver implements CataloguePriceResolverInterface
{
    private const DEFAULT_STRATEGY = 'default';

    /**
     * @var array<string, CataloguePriceStrategyInterface>
     */
    private readonly array $strategies;

    /**
     * @param array<mixed> $strategies Each a CataloguePriceStrategyInterface, keyed by product type id
     * @throws \InvalidArgumentException When an entry is not a strategy, or no `default` strategy is registered
     */
    public function __construct(array $strategies)
    {
        $accepted = [];
        foreach ($strategies as $typeId => $strategy) {
            if (!$strategy instanceof CataloguePriceStrategyInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'The catalogue price strategy "%s" must implement %s.',
                    (string) $typeId,
                    CataloguePriceStrategyInterface::class
                ));
            }
            $accepted[(string) $typeId] = $strategy;
        }
        if (!isset($accepted[self::DEFAULT_STRATEGY])) {
            throw new \InvalidArgumentException('A "default" catalogue price strategy must be registered.');
        }
        $this->strategies = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function unitPrice(ProductInterface $product, bool $includingTax, float $quantity = 1.0): ?float
    {
        return ($this->strategies[(string) $product->getTypeId()] ?? $this->strategies[self::DEFAULT_STRATEGY])
            ->price($product, $includingTax, $quantity);
    }
}
