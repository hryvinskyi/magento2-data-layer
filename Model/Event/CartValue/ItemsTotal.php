<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event\CartValue;

use Hryvinskyi\DataLayer\Api\Event\CartValueInterface;
use Hryvinskyi\DataLayer\Api\Event\ItemsValueInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Values a cart event at its items' value, the rule of every cart event: Σ (price − discount) × quantity over the items
 * as reported, so on their tax basis and without shipping
 */
class ItemsTotal implements CartValueInterface
{
    /**
     * @param ItemsValueInterface $itemsValue
     */
    public function __construct(private readonly ItemsValueInterface $itemsValue)
    {
    }

    /**
     * @inheritDoc
     */
    public function valueOf(CartInterface $cart, array $items): float
    {
        return $this->itemsValue->value($items);
    }
}
