<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Product;

use Hryvinskyi\DataLayer\Api\Product\LineVariantResolverInterface;
use Hryvinskyi\DataLayer\Api\Product\LineVariantStrategyInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * Gets a line's variant text from the strategy registered for the line's product type
 *
 * Strategies are keyed by product type id; a type without a strategy of its own uses the `default` one, which must
 * be registered.
 */
class LineVariantResolver implements LineVariantResolverInterface
{
    private const DEFAULT_STRATEGY = 'default';

    /**
     * @var array<string, LineVariantStrategyInterface>
     */
    private readonly array $strategies;

    /**
     * @param array<mixed> $strategies Each a LineVariantStrategyInterface, keyed by product type id
     * @throws \InvalidArgumentException When an entry is not a strategy, or no `default` strategy is registered
     */
    public function __construct(array $strategies)
    {
        $accepted = [];
        foreach ($strategies as $typeId => $strategy) {
            if (!$strategy instanceof LineVariantStrategyInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'The line variant strategy "%s" must implement %s.',
                    (string) $typeId,
                    LineVariantStrategyInterface::class
                ));
            }
            $accepted[(string) $typeId] = $strategy;
        }
        if (!isset($accepted[self::DEFAULT_STRATEGY])) {
            throw new \InvalidArgumentException('A "default" line variant strategy must be registered.');
        }
        $this->strategies = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function variant(CartItemInterface|OrderItemInterface $line): ?string
    {
        return ($this->strategies[(string) $line->getProductType()] ?? $this->strategies[self::DEFAULT_STRATEGY])
            ->variant($line);
    }
}
