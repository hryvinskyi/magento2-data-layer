<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event;

use Hryvinskyi\DataLayer\Api\Event\CartLineEventBuilderInterface;
use Hryvinskyi\DataLayer\Api\Event\EcommerceEventAssemblerInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Magento\Quote\Api\Data\CartItemInterface;

/**
 * Builds `add_to_cart` for units added to a cart line, whether by adding a product or by raising a line's quantity
 *
 * One item with the units the event is about, `currency`, and `value` = the item's price times those units. A cart
 * that is not a shopping cart reports it under the name its kind gives it, or not at all.
 */
class AddToCartEventBuilder implements CartLineEventBuilderInterface
{
    public const EVENT_NAME = 'add_to_cart';

    /**
     * @param EcommerceEventAssemblerInterface $assembler
     */
    public function __construct(private readonly EcommerceEventAssemblerInterface $assembler)
    {
    }

    /**
     * @inheritDoc
     */
    public function build(CartItemInterface $line, float $quantity): ?EventInterface
    {
        return $this->assembler->forCartLine(self::EVENT_NAME, $line, $quantity);
    }
}
