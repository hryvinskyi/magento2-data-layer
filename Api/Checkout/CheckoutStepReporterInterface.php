<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Checkout;

/**
 * Reports the checkout steps a shopper completed (shipping information, payment information)
 *
 * Never throws: a failure is logged and the shopper's checkout goes on.
 */
interface CheckoutStepReporterInterface
{
    /**
     * Report that the shipping information of a cart was saved
     *
     * @param int $cartId
     * @return void
     */
    public function reportShippingInformation(int $cartId): void;

    /**
     * Report that the payment information of a cart was saved, once per cart and payment method
     *
     * @param int $cartId
     * @param string $methodCode The payment method that was saved
     * @return void
     */
    public function reportPaymentInformation(int $cartId, string $methodCode): void;

    /**
     * Report that the payment information of a guest's cart was saved, once per cart and payment method
     *
     * @param string $maskedCartId The id a guest's checkout knows its cart by
     * @param string $methodCode The payment method that was saved
     * @return void
     */
    public function reportGuestPaymentInformation(string $maskedCartId, string $methodCode): void;
}
