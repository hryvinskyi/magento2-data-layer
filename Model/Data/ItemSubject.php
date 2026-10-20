<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Data;

use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * What one GA4 item is built from, valid by construction
 *
 * An item comes from a cart line or from an order line, never from both.
 */
class ItemSubject implements ItemSubjectInterface
{
    /**
     * @param ProductInterface $product The catalogue product the shopper sees (a configurable line's parent)
     * @param int $storeId The store view the item is reported for
     * @param float $quantity
     * @param ProductInterface|null $childProduct The chosen child of a configurable or bundle line
     * @param CartItemInterface|null $quoteItem The cart line the item comes from
     * @param OrderItemInterface|null $orderItem The order line the item comes from
     * @param string|null $listId The id of the list the item is shown in
     * @param string|null $listName The name of the list the item is shown in
     * @param int|null $index The item's position in that list
     * @throws \InvalidArgumentException When both a cart line and an order line are given
     */
    public function __construct(
        private readonly ProductInterface $product,
        private readonly int $storeId,
        private readonly float $quantity = 1.0,
        private readonly ?ProductInterface $childProduct = null,
        private readonly ?CartItemInterface $quoteItem = null,
        private readonly ?OrderItemInterface $orderItem = null,
        private readonly ?string $listId = null,
        private readonly ?string $listName = null,
        private readonly ?int $index = null
    ) {
        if ($quoteItem !== null && $orderItem !== null) {
            throw new \InvalidArgumentException('An item comes from a cart line or an order line, not from both.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getProduct(): ProductInterface
    {
        return $this->product;
    }

    /**
     * @inheritDoc
     */
    public function getChildProduct(): ?ProductInterface
    {
        return $this->childProduct;
    }

    /**
     * @inheritDoc
     */
    public function getQuoteItem(): ?CartItemInterface
    {
        return $this->quoteItem;
    }

    /**
     * @inheritDoc
     */
    public function getOrderItem(): ?OrderItemInterface
    {
        return $this->orderItem;
    }

    /**
     * @inheritDoc
     */
    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * @inheritDoc
     */
    public function getListId(): ?string
    {
        return $this->listId;
    }

    /**
     * @inheritDoc
     */
    public function getListName(): ?string
    {
        return $this->listName;
    }

    /**
     * @inheritDoc
     */
    public function getIndex(): ?int
    {
        return $this->index;
    }

    /**
     * @inheritDoc
     */
    public function getStoreId(): int
    {
        return $this->storeId;
    }
}
