<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Event;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * The money parameters of an event that reports a transaction (`purchase`, `refund`), on the store's basis
 *
 * `value` is net of discounts, as GA4 revenue is: it starts from the grand total (less its tax when the store deducts
 * tax), which a discount has already reduced, or from the subtotal less the document's discount, as the store's value
 * basis says; it is never negative. `tax` and `shipping` are reported when the store includes them. Every amount is
 * rounded to two decimals and is in the currency of the document it comes from.
 */
interface TransactionMoneyInterface
{
    /**
     * Get `value`, and `tax` and `shipping` when the store reports them
     *
     * @param float $grandTotal The document's grand total
     * @param float $subtotal The document's subtotal
     * @param float $tax The document's tax amount
     * @param float $shipping The document's shipping amount
     * @param int $storeId The store view whose settings apply
     * @param float $discount The discount of the document's items excluding tax (a discount of shipping is not
     *     part of the subtotal and is left out); its sign is ignored (a document stores it as a negative amount).
     *     Only the subtotal basis subtracts it.
     * @return array<string, float> Keyed by GA4 parameter name
     */
    public function parameters(
        float $grandTotal,
        float $subtotal,
        float $tax,
        float $shipping,
        int $storeId,
        float $discount = 0.0
    ): array;

    /**
     * Get the money parameters of an order, from its own totals and its store view's settings
     *
     * Only the discount of the order's items counts, without the part of it that was tax: the order's discount less
     * its shipping discount, and its discount tax compensation less the shipping's.
     *
     * @param OrderInterface $order
     * @return array<string, float> Keyed by GA4 parameter name
     */
    public function forOrder(OrderInterface $order): array;
}
