<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\ItemField;

use Hryvinskyi\DataLayer\Api\Catalog\OptionLabelReaderInterface;

/**
 * Option labels from an array, recording every read
 */
class FakeOptionLabelReader implements OptionLabelReaderInterface
{
    /**
     * @var list<array{attributeCode: string, optionIds: array<int|string>, storeId: int}>
     */
    public array $calls = [];

    /**
     * @param array<int|string, string> $labels Option id to label
     */
    public function __construct(private readonly array $labels = [])
    {
    }

    /**
     * @inheritDoc
     */
    public function labels(string $attributeCode, array $optionIds, int $storeId): array
    {
        $this->calls[] = ['attributeCode' => $attributeCode, 'optionIds' => $optionIds, 'storeId' => $storeId];

        return array_intersect_key($this->labels, array_flip($optionIds));
    }
}
