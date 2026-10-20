<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Catalog;

use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Loads the catalogue products behind order or credit memo lines, all of them in one product collection
 *
 * Products come with what an item reports from them (name, URL key, the store's identifier and brand attributes), so
 * reporting the lines of an order never loads a product per line.
 */
interface LineProductLoaderInterface
{
    /**
     * Load products by id for a store view
     *
     * @param list<int> $productIds
     * @param int $storeId The store view whose values are loaded
     * @return array<int, ProductInterface> Product id to product; a product that no longer exists is missing
     */
    public function byIds(array $productIds, int $storeId): array;
}
