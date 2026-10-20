<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Plugin\Checkout;

use Hryvinskyi\DataLayer\Api\Checkout\CheckoutStepReporterInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;

/**
 * Reports the shipping step once a cart's shipping information is saved
 *
 * A guest's checkout saves through this same service with the cart's real id, so one plugin covers both.
 */
class ShippingInformationPlugin
{
    /**
     * @param CheckoutStepReporterInterface $reporter
     */
    public function __construct(private readonly CheckoutStepReporterInterface $reporter)
    {
    }

    /**
     * Report the step after the save
     *
     * @param ShippingInformationManagementInterface $subject
     * @param mixed $result The saved cart's payment details
     * @param mixed $cartId
     * @return mixed The save's own result
     */
    public function afterSaveAddressInformation(
        ShippingInformationManagementInterface $subject,
        mixed $result,
        mixed $cartId
    ): mixed {
        if (is_numeric($cartId)) {
            $this->reporter->reportShippingInformation((int) $cartId);
        }

        return $result;
    }
}
