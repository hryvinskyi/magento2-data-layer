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
 * Builds `view_item` for the product a product page shows, in the current store view
 */
interface ViewItemEventBuilderInterface
{
    /**
     * Build the event
     *
     * @param ProductInterface $product
     * @return EventInterface|null Null when the event is disabled for the store, the product makes no item, or its
     *     price is hidden and the store skips such events
     */
    public function build(ProductInterface $product): ?EventInterface;
}
