<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event\CartValue;

use Hryvinskyi\DataLayer\Api\Event\CartValueInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Values a cart event at the cart's grand total in the cart's currency: tax and shipping included, discounts taken off,
 * as the cart's totals last collected it
 *
 * A cart whose totals were never collected stores no grand total and is worth zero.
 */
class GrandTotal implements CartValueInterface
{
    private const GRAND_TOTAL = 'grand_total';

    /**
     * @inheritDoc
     */
    public function valueOf(CartInterface $cart, array $items): float
    {
        $grandTotal = $cart instanceof DataObject ? $cart->getData(self::GRAND_TOTAL) : null;

        return is_numeric($grandTotal) ? round(max(0.0, (float) $grandTotal), 2) : 0.0;
    }
}
