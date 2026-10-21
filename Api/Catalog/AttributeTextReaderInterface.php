<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Catalog;

/**
 * Turns a product attribute's stored values into the text a shopper reads, many products in one go
 */
interface AttributeTextReaderInterface
{
    /**
     * Get the text of each product's stored value
     *
     * @param string $attributeCode
     * @param array<int, string> $values Product id to the value as stored; an option attribute stores option ids
     * @param int $storeId The store view whose text is read
     * @return array<int, string> Product id to text; a product whose value yields no text is left out
     */
    public function texts(string $attributeCode, array $values, int $storeId): array;
}
