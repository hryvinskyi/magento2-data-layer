<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Catalog;

use Hryvinskyi\DataLayer\Model\Catalog\CachedCategoryTreeSnapshot;
use Hryvinskyi\DataLayer\Model\Catalog\CategoryTree;
use Hryvinskyi\DataLayer\Model\Catalog\CategoryTreeFactory;
use Hryvinskyi\DataLayer\Observer\CleanCategoryTree;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The cached category tree: decoded once per store and request, kept in the application cache under its own key and
 * tags, rebuilt after an invalidation in the same request or from a corrupt entry
 */
class CachedCategoryTreeSnapshotTest extends TestCase
{
    /**
     * @var FakeCache
     */
    private FakeCache $cache;

    /**
     * @var FakeCategoryTreeSnapshot
     */
    private FakeCategoryTreeSnapshot $inner;

    /**
     * @var CachedCategoryTreeSnapshot
     */
    private CachedCategoryTreeSnapshot $snapshot;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->cache = new FakeCache($this->createMock(FrontendInterface::class));
        $this->inner = new FakeCategoryTreeSnapshot([
            1 => new CategoryTree([10 => ['path' => [10], 'level' => 2, 'position' => 1, 'name' => 'Computers']]),
            2 => new CategoryTree([10 => ['path' => [10], 'level' => 2, 'position' => 1, 'name' => 'Computer']]),
        ]);
        $treeFactory = $this->createMock(CategoryTreeFactory::class);
        $treeFactory->method('create')->willReturnCallback(
            static fn (array $data): CategoryTree => new CategoryTree(is_array($data['nodes']) ? $data['nodes'] : [])
        );
        $this->snapshot = new CachedCategoryTreeSnapshot($this->inner, $this->cache, new Json(), $treeFactory);
    }

    /**
     * The second read of a store in one request reaches neither the application cache nor the inner snapshot
     *
     * @return void
     */
    public function testSecondReadInARequestReachesNeitherTheCacheNorTheInnerSnapshot(): void
    {
        $first = $this->snapshot->forStore(1);
        $second = $this->snapshot->forStore(1);

        self::assertSame([1], $this->inner->askedStores);
        self::assertSame(1, $this->cache->loads);
        self::assertSame($first, $second);
        self::assertSame(['Computers'], $second->pathNames(10));
    }

    /**
     * A new request served by the same process decodes the tree from the application cache again, without
     * reading the catalogue
     *
     * @return void
     */
    public function testResetAfterRequestReadsTheApplicationCacheAgain(): void
    {
        $first = $this->snapshot->forStore(1);

        $this->snapshot->_resetState();
        $second = $this->snapshot->forStore(1);

        self::assertSame([1], $this->inner->askedStores);
        self::assertSame(2, $this->cache->loads);
        self::assertNotSame($first, $second);
        self::assertEquals($first, $second);
    }

    /**
     * Each store has its own entry, with the tree's tag, the category tag and the module tag, for a day at most
     *
     * @return void
     */
    public function testEachStoreHasItsOwnTaggedEntry(): void
    {
        self::assertSame(['Computers'], $this->snapshot->forStore(1)->pathNames(10));
        self::assertSame(['Computer'], $this->snapshot->forStore(2)->pathNames(10));

        self::assertSame([1, 2], $this->inner->askedStores);
        self::assertSame(
            ['hryvinskyi_datalayer_category_tree_1', 'hryvinskyi_datalayer_category_tree_2'],
            array_keys($this->cache->entries)
        );
        $entry = $this->cache->entries['hryvinskyi_datalayer_category_tree_1'];
        self::assertSame(['hryvinskyi_datalayer_category_tree', 'cat_c', 'hryvinskyi_datalayer'], $entry['tags']);
        self::assertSame(86400, $entry['lifetime']);
    }

    /**
     * An entry that does not hold a valid tree counts as a miss and is replaced
     *
     * @param string $stored
     * @return void
     */
    #[DataProvider('corruptEntries')]
    public function testCorruptEntryIsTreatedAsAMiss(string $stored): void
    {
        $this->cache->save($stored, 'hryvinskyi_datalayer_category_tree_1');

        self::assertSame(['Computers'], $this->snapshot->forStore(1)->pathNames(10));
        self::assertSame([1], $this->inner->askedStores);
        self::assertNotSame($stored, $this->cache->load('hryvinskyi_datalayer_category_tree_1'));
    }

    /**
     * Stored values that are not a tree
     *
     * @return array<string, array{string}>
     */
    public static function corruptEntries(): array
    {
        return [
            'not JSON' => ['{"10":'],
            'not a list of nodes' => ['"text"'],
            'a node of the wrong shape' => ['{"10":{"path":[11],"level":2,"position":1,"name":"x"}}'],
        ];
    }

    /**
     * After the category observer has run, the next read in the same request rebuilds from the catalogue
     *
     * @return void
     */
    public function testReadAfterTheObserverInTheSameRequestRebuilds(): void
    {
        $this->snapshot->forStore(1);

        (new CleanCategoryTree($this->snapshot))->execute(new Observer());
        $this->snapshot->forStore(1);

        self::assertSame([['hryvinskyi_datalayer_category_tree']], $this->cache->cleanedTags);
        self::assertSame([1, 1], $this->inner->askedStores);
        self::assertSame(2, $this->cache->loads);
    }

    /**
     * Invalidating drops the trees of every store, not only the last one read
     *
     * @return void
     */
    public function testInvalidatingDropsEveryStore(): void
    {
        $this->snapshot->forStore(1);
        $this->snapshot->forStore(2);

        $this->snapshot->invalidate();
        $this->snapshot->forStore(1);
        $this->snapshot->forStore(2);

        self::assertSame([1, 2, 1, 2], $this->inner->askedStores);
    }
}
