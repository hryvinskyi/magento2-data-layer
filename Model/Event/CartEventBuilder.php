<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event;

use Hryvinskyi\DataLayer\Api\Event\CartEventBuilderInterface;
use Hryvinskyi\DataLayer\Api\Event\EcommerceEventAssemblerInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Builds an event that reports a whole cart (`view_cart`, `begin_checkout`: the event name is configured)
 *
 * The cart's items, `currency`, `value` (Σ (price − discount) × quantity over its items, on their tax basis) and
 * `coupon`, under the rules every whole-cart event shares: one item per visible line, a cart that is not tracked or
 * has no line makes no event, and a hidden price leaves out `value` or skips the event, as the store's setting says.
 * A cart that is not a shopping cart reports the event under the name its kind gives it, or not at all.
 */
class CartEventBuilder implements CartEventBuilderInterface
{
    /**
     * @param string $eventName The GA4 event this builder reports
     * @param EcommerceEventAssemblerInterface $assembler
     */
    public function __construct(
        private readonly string $eventName,
        private readonly EcommerceEventAssemblerInterface $assembler
    ) {
    }

    /**
     * @inheritDoc
     */
    public function build(CartInterface $cart): ?EventInterface
    {
        return $this->assembler->forCart($this->eventName, $cart);
    }
}
