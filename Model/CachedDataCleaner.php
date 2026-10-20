<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\CachedDataCleanerInterface;
use Hryvinskyi\DataLayer\Api\Catalog\CategoryTreeInvalidatorInterface;
use Hryvinskyi\DataLayer\Model\Catalog\CachedCategoryTreeSnapshot;
use Magento\Framework\App\CacheInterface;

/**
 * Cleans the tag every data layer cache entry carries, and empties the category trees decoded in this process
 */
class CachedDataCleaner implements CachedDataCleanerInterface
{
    /**
     * @param CacheInterface $cache
     * @param CategoryTreeInvalidatorInterface $categoryTrees
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly CategoryTreeInvalidatorInterface $categoryTrees
    ) {
    }

    /**
     * @inheritDoc
     */
    public function clean(): void
    {
        $this->cache->clean([CachedCategoryTreeSnapshot::MODULE_CACHE_TAG]);
        $this->categoryTrees->invalidate();
    }
}
