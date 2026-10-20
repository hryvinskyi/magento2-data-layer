<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\CategoryTreeInvalidatorInterface;
use Hryvinskyi\DataLayer\Api\Catalog\CategoryTreeSnapshotInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Keeps each store view's category tree in two tiers, so a warm page reads no category at all and decodes each
 * store's tree once
 *
 * The first tier holds the decoded tree per store for the rest of the process's request: every event a page builds
 * shares it. The second is the application cache, shared by all processes. An entry there is tagged with this
 * tree's own tag, with the catalogue's category tag and with the data layer's tag. It lives a day at most, which
 * bounds how long a category written past the category model (an import writing straight to the tables) can be
 * reported stale. An entry that cannot be read back is treated as missing and rebuilt.
 *
 * Invalidating empties both tiers, so a read later in the same request or process rebuilds; resetting after a
 * request (an application server serving many requests from one process) empties the first tier only.
 */
class CachedCategoryTreeSnapshot implements
    CategoryTreeSnapshotInterface,
    CategoryTreeInvalidatorInterface,
    ResetAfterRequestInterface
{
    /**
     * Tag of every cached category tree; cleaning it makes the next read rebuild
     */
    public const CACHE_TAG = 'hryvinskyi_datalayer_category_tree';

    /**
     * Tag shared by everything the data layer caches
     */
    public const MODULE_CACHE_TAG = 'hryvinskyi_datalayer';

    /**
     * Seconds an entry lives at most
     */
    public const LIFETIME = 86400;

    private const KEY_PREFIX = 'hryvinskyi_datalayer_category_tree_';

    /**
     * Decoded trees of this request, by store id; the only mutable state, emptied by invalidating or resetting
     *
     * @var array<int, CategoryTree>
     */
    private array $trees = [];

    /**
     * @param CategoryTreeSnapshotInterface $inner The snapshot that reads the tree when it is not cached
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param CategoryTreeFactory $treeFactory
     */
    public function __construct(
        private readonly CategoryTreeSnapshotInterface $inner,
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer,
        private readonly CategoryTreeFactory $treeFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function forStore(int $storeId): CategoryTree
    {
        return $this->trees[$storeId] ??= $this->sharedTree($storeId);
    }

    /**
     * @inheritDoc
     */
    public function invalidate(): void
    {
        $this->trees = [];
        $this->cache->clean([self::CACHE_TAG]);
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->trees = [];
    }

    /**
     * Get the store's tree from the application cache, reading and caching it on a miss
     *
     * @param int $storeId
     * @return CategoryTree
     * @throws LocalizedException When the tree cannot be read on a miss
     */
    private function sharedTree(int $storeId): CategoryTree
    {
        $key = self::KEY_PREFIX . $storeId;
        $cached = $this->cachedTree($key);
        if ($cached !== null) {
            return $cached;
        }

        $tree = $this->inner->forStore($storeId);
        $serialized = $this->serializer->serialize($tree->toArray());
        if (is_string($serialized)) {
            $this->cache->save(
                $serialized,
                $key,
                [self::CACHE_TAG, Category::CACHE_TAG, self::MODULE_CACHE_TAG],
                self::LIFETIME
            );
        }

        return $tree;
    }

    /**
     * Read a cached tree back; anything that is not a valid tree counts as a miss
     *
     * @param string $key
     * @return CategoryTree|null
     */
    private function cachedTree(string $key): ?CategoryTree
    {
        $data = (string) $this->cache->load($key);
        if ($data === '') {
            return null;
        }

        try {
            $nodes = $this->serializer->unserialize($data);

            return is_array($nodes) ? $this->treeFactory->create(['nodes' => $nodes]) : null;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
