<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Catalog;

/**
 * Reads the category path GA4 items report, for many products in one go
 */
interface CategoryPathReaderInterface
{
    /**
     * Get each product's category path
     *
     * @param list<int> $productIds
     * @param int $storeId The store view whose category names are read
     * @param array<int,int> $preferredCategoryIds Product id to the category id to prefer for that product
     * @return array<int, list<string>> Product id to category names, top level first, at most five
     */
    public function read(array $productIds, int $storeId, array $preferredCategoryIds = []): array;
}
