<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Event;

use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Builds the event of a shopper acting on a quantity of a catalogue product outside the cart (adding it to a wish
 * list)
 */
interface ProductQuantityEventBuilderInterface
{
    /**
     * Build the event for a product and a quantity
     *
     * @param ProductInterface $product
     * @param float $quantity
     * @param int $storeId The store view the event belongs to
     * @return EventInterface|null Null when the event is disabled, the product yields no item or the hidden-price
     *     setting skips the event
     */
    public function build(ProductInterface $product, float $quantity, int $storeId): ?EventInterface;
}
