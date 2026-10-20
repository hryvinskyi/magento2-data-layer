<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event;

use Hryvinskyi\DataLayer\Api\Event\CartValueInterface;
use Hryvinskyi\DataLayer\Api\Event\CheckoutStepEventBuilderInterface;
use Hryvinskyi\DataLayer\Api\Event\EcommerceEventAssemblerInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * Builds `add_payment_info` for a cart whose payment information was saved
 *
 * The cart's items, `currency` and `coupon` as for the cart itself, `value` as the store view's checkout step value
 * basis says (the items' value, or the cart's grand total), plus `payment_type`: the selected payment method's title
 * in the cart's store view, or its code when the method cannot give one; left out when no method is selected.
 */
class AddPaymentInfoEventBuilder implements CheckoutStepEventBuilderInterface
{
    public const EVENT_NAME = 'add_payment_info';

    /**
     * @param EventAvailabilityInterface $availability
     * @param EcommerceEventAssemblerInterface $assembler
     * @param CartValueInterface $stepValue What a checkout step's cart is worth
     */
    public function __construct(
        private readonly EventAvailabilityInterface $availability,
        private readonly EcommerceEventAssemblerInterface $assembler,
        private readonly CartValueInterface $stepValue
    ) {
    }

    /**
     * @inheritDoc
     */
    public function build(CartInterface $cart): ?EventInterface
    {
        if (!$cart instanceof Quote || !$this->availability->isEnabled(self::EVENT_NAME, (int) $cart->getStoreId())) {
            return null;
        }

        $type = $this->paymentType($cart);

        return $this->assembler->forCart(
            self::EVENT_NAME,
            $cart,
            $type !== '' ? ['payment_type' => $type] : [],
            $this->stepValue
        );
    }

    /**
     * Get the selected payment method's title, or its code
     *
     * @param Quote $cart
     * @return string Empty when no payment method is selected
     */
    private function paymentType(Quote $cart): string
    {
        $payment = $cart->getPayment();
        $code = trim((string) $payment->getMethod());
        if ($code === '') {
            return '';
        }

        try {
            $title = trim((string) $payment->getMethodInstance()->getTitle());
        } catch (LocalizedException) {
            $title = '';
        }

        return $title !== '' ? $title : $code;
    }
}
