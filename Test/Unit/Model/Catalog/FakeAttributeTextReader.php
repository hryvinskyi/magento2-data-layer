<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\AttributeTextReaderInterface;

/**
 * Attribute text marked with the reader's name, recording every read
 */
class FakeAttributeTextReader implements AttributeTextReaderInterface
{
    /**
     * @var list<array{attributeCode: string, values: array<int, string>, storeId: int}>
     */
    public array $calls = [];

    /**
     * @param string $name Prefixed to every text, so a test can tell which reader produced it
     */
    public function __construct(private readonly string $name)
    {
    }

    /**
     * @inheritDoc
     */
    public function texts(string $attributeCode, array $values, int $storeId): array
    {
        $this->calls[] = ['attributeCode' => $attributeCode, 'values' => $values, 'storeId' => $storeId];

        return array_map(fn (string $value): string => $this->name . ':' . $value, $values);
    }
}
