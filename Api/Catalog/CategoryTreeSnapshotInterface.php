<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Catalog;

use Hryvinskyi\DataLayer\Model\Catalog\CategoryTree;
use Magento\Framework\Exception\LocalizedException;

/**
 * Gives the active category tree of a store view, below its root catalog
 */
interface CategoryTreeSnapshotInterface
{
    /**
     * Get the active categories of the store view's catalog, with their names in that store view
     *
     * @param int $storeId
     * @return CategoryTree
     * @throws LocalizedException When the store view or the category metadata cannot be resolved
     */
    public function forStore(int $storeId): CategoryTree;
}
