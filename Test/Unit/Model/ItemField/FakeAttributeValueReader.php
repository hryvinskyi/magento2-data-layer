<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\ItemField;

use Hryvinskyi\DataLayer\Api\Catalog\AttributeValueReaderInterface;

/**
 * Attribute values from an array, recording every read
 */
class FakeAttributeValueReader implements AttributeValueReaderInterface
{
    /**
     * @var list<array{productIds: list<int>, attributeCode: string, storeId: int}>
     */
    public array $calls = [];

    /**
     * @param array<string, array<int, string>> $values Attribute code to product id to raw value
     */
    public function __construct(private readonly array $values = [])
    {
    }

    /**
     * @inheritDoc
     */
    public function read(array $productIds, string $attributeCode, int $storeId): array
    {
        $this->calls[] = ['productIds' => $productIds, 'attributeCode' => $attributeCode, 'storeId' => $storeId];

        return array_intersect_key($this->values[$attributeCode] ?? [], array_flip($productIds));
    }
}
