<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Plugin\Checkout;

use Hryvinskyi\DataLayer\Api\Checkout\CheckoutStepReporterInterface;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Reports the payment step once a guest's payment information is saved
 *
 * The guest service saves on its own rather than through the signed-in customer's, so it needs its own plugin. Only
 * a save that happened is reported: the service answers false, without saving, when too many saves come too fast.
 * Placing the order saves the payment information again; the reporter reports each cart and payment method once.
 */
class GuestPaymentInformationPlugin
{
    /**
     * @param CheckoutStepReporterInterface $reporter
     */
    public function __construct(private readonly CheckoutStepReporterInterface $reporter)
    {
    }

    /**
     * Report the step after a save that happened
     *
     * @param GuestPaymentInformationManagementInterface $subject
     * @param mixed $result True when the payment information was saved
     * @param mixed $cartId The id a guest's checkout knows its cart by
     * @param mixed $email The guest's email address, which the step does not report
     * @param PaymentInterface $paymentMethod
     * @return mixed The save's own result
     */
    public function afterSavePaymentInformation(
        GuestPaymentInformationManagementInterface $subject,
        mixed $result,
        mixed $cartId,
        mixed $email,
        PaymentInterface $paymentMethod
    ): mixed {
        if ($result === true && is_string($cartId) && $cartId !== '') {
            $this->reporter->reportGuestPaymentInformation($cartId, (string) $paymentMethod->getMethod());
        }

        return $result;
    }
}
