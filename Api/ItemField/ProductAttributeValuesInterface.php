<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\ItemField;

use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Gets one attribute's raw value for many products, reading only what the loaded products do not already carry
 */
interface ProductAttributeValuesInterface
{
    /**
     * Get the attribute's raw value for each product
     *
     * A value the product was loaded with is used as it is; the values of every other product are read together in
     * one go. `entity_id` is the product id.
     *
     * @param array<int,ProductInterface> $products Keyed by product id
     * @param string $attributeCode
     * @param int $storeId The store view whose values are read
     * @return array<int, string> Product id to raw value (option ids stay ids); a product without a value is left out
     */
    public function values(array $products, string $attributeCode, int $storeId): array;
}
