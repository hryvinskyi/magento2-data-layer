<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event;

use Hryvinskyi\DataLayer\Api\Event\EcommerceEventAssemblerInterface;
use Hryvinskyi\DataLayer\Api\Event\ProductQuantityEventBuilderInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Builds `add_to_wishlist` for a product the shopper put on a wish list
 *
 * One item with the wished quantity, `currency` (the store view's current one), and `value` = the item's price times
 * that quantity.
 */
class AddToWishlistEventBuilder implements ProductQuantityEventBuilderInterface
{
    public const EVENT_NAME = 'add_to_wishlist';

    /**
     * @param EcommerceEventAssemblerInterface $assembler
     */
    public function __construct(private readonly EcommerceEventAssemblerInterface $assembler)
    {
    }

    /**
     * @inheritDoc
     */
    public function build(ProductInterface $product, float $quantity, int $storeId): ?EventInterface
    {
        return $this->assembler->forProduct(self::EVENT_NAME, $product, $quantity, $storeId);
    }
}
