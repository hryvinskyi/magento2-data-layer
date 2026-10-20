<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Cart;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Names the event a cart reports, by the cart's kind
 *
 * Every cart event is built under a shopping cart's name (`add_to_cart`, `view_cart`, …). A shopping cart reports it
 * under that name; a cart of a registered kind reports the event its kind names in its place, or nothing.
 */
interface CartEventNamingInterface
{
    /**
     * Get the event a cart reports for what a shopping cart reports as the given event
     *
     * @param string $cartEvent The shopping cart's event
     * @param CartInterface $cart
     * @return string|null The shopping cart's event for a shopping cart, the kind's own event for a cart of a
     *     registered kind; null when the cart's kind reports no such event
     */
    public function eventFor(string $cartEvent, CartInterface $cart): ?string;
}
