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
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * Builds `add_shipping_info` for a cart whose shipping information was saved
 *
 * The cart's items, `currency` and `coupon` as for the cart itself, `value` as the store view's checkout step value
 * basis says (the items' value, or the cart's grand total), plus `shipping_tier`: the selected shipping method's
 * description, or its code when it has none; left out when no method is selected.
 */
class AddShippingInfoEventBuilder implements CheckoutStepEventBuilderInterface
{
    public const EVENT_NAME = 'add_shipping_info';

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

        $tier = $this->shippingTier($cart);

        return $this->assembler->forCart(
            self::EVENT_NAME,
            $cart,
            $tier !== '' ? ['shipping_tier' => $tier] : [],
            $this->stepValue
        );
    }

    /**
     * Get the selected shipping method's description, or its code
     *
     * @param Quote $cart
     * @return string Empty when no shipping method is selected
     */
    private function shippingTier(Quote $cart): string
    {
        $address = $cart->getShippingAddress();
        $description = $address->getData('shipping_description');
        if (is_string($description) && trim($description) !== '') {
            return trim($description);
        }
        $code = $address->getData('shipping_method');

        return is_string($code) ? trim($code) : '';
    }
}
