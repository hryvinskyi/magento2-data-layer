<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\ItemField;

use Hryvinskyi\DataLayer\Api\Catalog\CategoryPathReaderInterface;

/**
 * Category paths from an array, recording every read
 */
class FakeCategoryPathReader implements CategoryPathReaderInterface
{
    /**
     * @var list<array{productIds: list<int>, storeId: int, preferred: array<int,int>}>
     */
    public array $calls = [];

    /**
     * @param array<int, list<string>> $paths Product id to category names
     */
    public function __construct(private readonly array $paths = [])
    {
    }

    /**
     * @inheritDoc
     */
    public function read(array $productIds, int $storeId, array $preferredCategoryIds = []): array
    {
        $this->calls[] = ['productIds' => $productIds, 'storeId' => $storeId, 'preferred' => $preferredCategoryIds];

        return array_intersect_key($this->paths, array_flip($productIds));
    }
}
