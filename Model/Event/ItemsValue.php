<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event;

use Hryvinskyi\DataLayer\Api\Event\ItemsValueInterface;

/**
 * Adds up the items' price less discount, times quantity, from their reported parameters
 *
 * The value is never negative: a discount larger than the price it is reported against (figures read between a
 * quantity change and the cart's next totals) does not turn a cart event into a negative amount.
 */
class ItemsValue implements ItemsValueInterface
{
    /**
     * @inheritDoc
     */
    public function value(array $items): float
    {
        $value = 0.0;
        foreach ($items as $item) {
            $parameters = $item->getParameters();
            $price = $this->number($parameters['price'] ?? null);
            if ($price === null) {
                continue;
            }
            $discount = $this->number($parameters['discount'] ?? null) ?? 0.0;
            $value += ($price - $discount) * ($this->number($parameters['quantity'] ?? null) ?? 0.0);
        }

        return max(0.0, round($value, 2));
    }

    /**
     * Read a reported amount
     *
     * @param mixed $value
     * @return float|null Null when the parameter holds no number
     */
    private function number(mixed $value): ?float
    {
        return is_int($value) || is_float($value) ? (float) $value : null;
    }
}
