<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Event;

use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;

/**
 * Assembles the ecommerce events detected on the server from a cart line, a whole cart or a catalogue product
 *
 * A cart event is asked for under a shopping cart's name (`add_to_cart`, `view_cart`, …) and reported under the name
 * the cart's kind gives it; a cart whose kind reports no such event makes none. Each method checks first that the
 * event, under the name it is reported by, is enabled for the store view (nothing else is done for a disabled event),
 * then that the cart is tracked. Money follows the hidden-price rule: when a product's price is hidden from the
 * viewer the event loses `value` (its items lose `price` and `discount`) or is skipped, as the store's setting says.
 * An event's `value` is Σ (price − discount) × quantity over its items as reported, so it is on the same tax basis
 * as their prices; only a caller that asks for another value of a whole cart (a checkout step) gets that instead.
 */
interface EcommerceEventAssemblerInterface
{
    /**
     * Assemble an event about a quantity of one cart line
     *
     * One item, `currency`, and `value` = the item's price less its discount, times the quantity.
     *
     * @param string $eventName The shopping cart's event
     * @param CartItemInterface $line
     * @param float $quantity How many units the event is about, positive
     * @return EventInterface|null Null when the cart's kind reports no such event, the event is disabled, the cart is
     *     not tracked, the quantity is not positive, the line yields no item or the event is skipped for a hidden price
     */
    public function forCartLine(string $eventName, CartItemInterface $line, float $quantity): ?EventInterface;

    /**
     * Assemble an event about a whole cart
     *
     * One item per visible line with the line's quantity, `currency`, `value`, `coupon` when one is applied, then the
     * given parameters. The value is Σ over the items of price less discount, times quantity, unless another cart value
     * is given; either way it is left out with the money when a price is hidden from the viewer.
     *
     * @param string $eventName The shopping cart's event
     * @param CartInterface $cart
     * @param array<string,string> $parameters Further event parameters (a checkout step's own)
     * @param CartValueInterface|null $value What the cart is worth to this event; null for the items' value
     * @return EventInterface|null Null when the cart's kind reports no such event, the event is disabled, the cart is
     *     not tracked or has no item, or the event is skipped for a hidden price
     */
    public function forCart(
        string $eventName,
        CartInterface $cart,
        array $parameters = [],
        ?CartValueInterface $value = null
    ): ?EventInterface;

    /**
     * Assemble an event about a quantity of a catalogue product
     *
     * One item, `currency` (the store view's current one), and `value` = the item's price times the quantity.
     *
     * @param string $eventName
     * @param ProductInterface $product
     * @param float $quantity
     * @param int $storeId
     * @return EventInterface|null Null when the event is disabled, the quantity is not positive, the product yields
     *     no item or the event is skipped for a hidden price
     */
    public function forProduct(
        string $eventName,
        ProductInterface $product,
        float $quantity,
        int $storeId
    ): ?EventInterface;
}
