<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Catalog;

/**
 * Reads the store labels of an attribute's options, many options in one go
 */
interface OptionLabelReaderInterface
{
    /**
     * Get the store label of each option
     *
     * @param string $attributeCode
     * @param array<int|string> $optionIds
     * @param int $storeId The store view whose labels are read
     * @return array<int|string, string> Option id to store label
     */
    public function labels(string $attributeCode, array $optionIds, int $storeId): array;
}
