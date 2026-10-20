<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\CategoryTreeSnapshotInterface;
use Hryvinskyi\DataLayer\Model\Catalog\CategoryTree;

/**
 * Hands out a fixed tree per store, recording the stores it was asked for
 */
class FakeCategoryTreeSnapshot implements CategoryTreeSnapshotInterface
{
    /**
     * @var list<int>
     */
    public array $askedStores = [];

    /**
     * @param array<int, CategoryTree> $trees Store id to tree
     */
    public function __construct(private readonly array $trees)
    {
    }

    /**
     * @inheritDoc
     */
    public function forStore(int $storeId): CategoryTree
    {
        $this->askedStores[] = $storeId;

        return $this->trees[$storeId] ?? new CategoryTree();
    }
}
