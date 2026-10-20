<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Checkout;

/**
 * Remembers in the visitor's session which cart and payment method the payment step was last reported for
 *
 * Saving the payment information runs again when the order is placed, and some payment methods save it twice before
 * that; the step is reported once per cart and payment method.
 */
interface PaymentInfoLedgerInterface
{
    /**
     * Whether the payment step was last reported for this cart and payment method
     *
     * @param int $cartId
     * @param string $methodCode
     * @return bool
     */
    public function wasReported(int $cartId, string $methodCode): bool;

    /**
     * Record that the payment step was reported for this cart and payment method
     *
     * @param int $cartId
     * @param string $methodCode
     * @return void
     */
    public function markReported(int $cartId, string $methodCode): void;
}
