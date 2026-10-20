<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Catalog;

/**
 * Reads one product attribute's stored values for many products in one go
 */
interface AttributeValueReaderInterface
{
    /**
     * Get the attribute's raw value for each product
     *
     * @param list<int> $productIds
     * @param string $attributeCode
     * @param int $storeId The store view whose values are read
     * @return array<int, string> Product id to raw value; option ids stay ids
     */
    public function read(array $productIds, string $attributeCode, int $storeId): array;
}
