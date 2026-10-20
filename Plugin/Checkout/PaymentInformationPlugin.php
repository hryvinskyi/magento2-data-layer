<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Plugin\Checkout;

use Hryvinskyi\DataLayer\Api\Checkout\CheckoutStepReporterInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Reports the payment step once a signed-in customer's payment information is saved
 *
 * Only a save that happened is reported: the service answers false, without saving, when too many saves come too
 * fast. Placing the order saves the payment information again; the reporter reports each cart and payment method once.
 */
class PaymentInformationPlugin
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
     * @param PaymentInformationManagementInterface $subject
     * @param mixed $result True when the payment information was saved
     * @param mixed $cartId
     * @param PaymentInterface $paymentMethod
     * @return mixed The save's own result
     */
    public function afterSavePaymentInformation(
        PaymentInformationManagementInterface $subject,
        mixed $result,
        mixed $cartId,
        PaymentInterface $paymentMethod
    ): mixed {
        if ($result === true && is_numeric($cartId)) {
            $this->reporter->reportPaymentInformation((int) $cartId, (string) $paymentMethod->getMethod());
        }

        return $result;
    }
}
