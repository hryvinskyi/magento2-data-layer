<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Cart;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;

/**
 * Remembers, for the current request, the cart lines an addition re-priced whose cart totals have not run since
 *
 * Adding a product creates or raises a line and gives it the product's raw final price: before the cart's totals
 * run, that price has no tier price for the line's quantity, no tax basis and no discount. Once the totals ran for the
 * cart, its lines carry their calculated figures again.
 */
interface RepricedLinesInterface
{
    /**
     * Remember that an addition re-priced a line
     *
     * @param CartItemInterface $line
     * @return void
     */
    public function markRepriced(CartItemInterface $line): void;

    /**
     * Forget every line of a cart whose totals have just run
     *
     * @param CartInterface $cart
     * @return void
     */
    public function markTotalsCollected(CartInterface $cart): void;

    /**
     * Whether a line was re-priced by an addition and its cart's totals have not run since
     *
     * @param CartItemInterface $line
     * @return bool
     */
    public function isAwaitingTotals(CartItemInterface $line): bool;
}
