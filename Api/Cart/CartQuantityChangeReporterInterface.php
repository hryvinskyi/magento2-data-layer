<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Cart;

use Magento\Quote\Api\Data\CartItemInterface;

/**
 * Reports a change of a cart line's quantity: an increase as a product added to the cart, a decrease as a product
 * removed from it
 *
 * Never throws: a failure is logged and the shopper's cart action goes on.
 */
interface CartQuantityChangeReporterInterface
{
    /**
     * Report how much a cart line's quantity changed
     *
     * @param CartItemInterface $line The cart line (the parent line of a configurable or bundle product)
     * @param float $change Positive for units added, negative for units removed; zero reports nothing
     * @return void
     */
    public function report(CartItemInterface $line, float $change): void;
}
