<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\CategoryTreeInvalidatorInterface;
use Hryvinskyi\DataLayer\Api\Catalog\CategoryTreeSnapshotInterface;
use Hryvinskyi\DataLayer\Model\Catalog\CachedCategoryTreeSnapshot;
use Hryvinskyi\DataLayer\Model\Catalog\CategoryTree;
use Hryvinskyi\DataLayerApi\Api\BlockListDataInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\View\Element\Text;
use Magento\Framework\View\LayoutInterface;
use PHPUnit\Framework\TestCase;

/**
 * The cached category tree is dropped by the module's own observer whenever a category is saved, deleted or moved,
 * including the ordinary saves (a rename, an activation toggle) that clean only the saved category's own tag
 *
 * @magentoDbIsolation enabled
 * @magentoDataFixture Magento/Catalog/_files/categories_no_products.php
 */
class CategoryTreeCacheTest extends TestCase
{
    use CatalogFixtures;

    private const STORE_ID = 1;

    private const CACHE_KEY = 'hryvinskyi_datalayer_category_tree_1';

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->cleanCategoryTrees();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->cleanCategoryTrees();
    }

    /**
     * The tree port resolves to the cache in front of the database, and the invalidator to that same shared
     * instance, so an invalidation empties the trees readers hold in this request
     *
     * @return void
     */
    public function testPortAndInvalidatorShareTheCache(): void
    {
        self::assertInstanceOf(CachedCategoryTreeSnapshot::class, $this->snapshot());
        self::assertSame($this->snapshot(), $this->service(CategoryTreeInvalidatorInterface::class));
    }

    /**
     * A second read in the same request returns the tree already decoded
     *
     * @return void
     */
    public function testSecondReadInARequestReturnsTheSameTree(): void
    {
        $tree = $this->warmTree();

        self::assertSame($tree, $this->snapshot()->forStore(self::STORE_ID));
    }

    /**
     * Renaming a category through its repository drops the cached tree; the next read has the new name
     *
     * @return void
     */
    public function testRenameThroughTheRepositoryRebuildsTheTree(): void
    {
        self::assertSame(['Category 1', 'Category 1.1'], $this->warmTree()->pathNames(4));

        $repository = $this->service(CategoryRepositoryInterface::class);
        $category = $repository->get(4);
        $category->setName('Category 1.1 Renamed');
        $repository->save($category);

        self::assertSame('', (string) $this->service(CacheInterface::class)->load(self::CACHE_KEY));
        self::assertSame(
            ['Category 1', 'Category 1.1 Renamed'],
            $this->snapshot()->forStore(self::STORE_ID)->pathNames(4)
        );
    }

    /**
     * Renaming a category through its repository also drops the list data cached beside a block-cached block: its
     * items name their categories
     *
     * @magentoCache block_html enabled
     * @return void
     */
    public function testRenameDropsCachedListData(): void
    {
        $block = $this->checked(
            $this->service(LayoutInterface::class)->createBlock(Text::class, 'datalayer_rename_list'),
            Text::class
        );
        $block->setData('cache_lifetime', 3600);
        $listData = $this->service(BlockListDataInterface::class);
        $render = static fn (): string => '<script type="application/json">{"items":[]}</script>';
        $listData->append($block, '<ol></ol>', $render);
        $key = 'hryvinskyi_datalayer_list_' . sha1((string) $block->getCacheKey());
        self::assertNotSame('', (string) $this->service(CacheInterface::class)->load($key));

        $repository = $this->service(CategoryRepositoryInterface::class);
        $category = $repository->get(4);
        $category->setName('Category 1.1 Listed');
        $repository->save($category);

        self::assertSame('', (string) $this->service(CacheInterface::class)->load($key));
    }

    /**
     * Deactivating a category through its repository drops the cached tree; the category is no longer reported
     *
     * @return void
     */
    public function testDeactivationThroughTheRepositoryRebuildsTheTree(): void
    {
        self::assertTrue($this->warmTree()->contains(6));

        $repository = $this->service(CategoryRepositoryInterface::class);
        $category = $repository->get(6);
        $category->setIsActive(false);
        $repository->save($category);

        self::assertSame('', (string) $this->service(CacheInterface::class)->load(self::CACHE_KEY));
        self::assertFalse($this->snapshot()->forStore(self::STORE_ID)->contains(6));
    }

    /**
     * The event a category move announces drops the cached tree
     *
     * The move itself is not run here: it reindexes every store synchronously, and this test install holds a store
     * without its category index table.
     *
     * @return void
     */
    public function testCategoryMoveEventDropsTheTree(): void
    {
        $warm = $this->warmTree();

        $this->service(EventManagerInterface::class)->dispatch('category_move', ['category_id' => 7]);

        self::assertSame('', (string) $this->service(CacheInterface::class)->load(self::CACHE_KEY));
        self::assertNotSame($warm, $this->snapshot()->forStore(self::STORE_ID));
    }

    /**
     * Deleting a category through its repository drops the cached tree; the category is no longer reported
     *
     * @magentoAppArea adminhtml
     * @return void
     */
    public function testDeleteThroughTheRepositoryRebuildsTheTree(): void
    {
        self::assertTrue($this->warmTree()->contains(12));

        $this->service(CategoryRepositoryInterface::class)->deleteByIdentifier(12);

        self::assertFalse($this->snapshot()->forStore(self::STORE_ID)->contains(12));
    }

    /**
     * Read the tree once, so it is cached, and check that it is
     *
     * @return CategoryTree
     */
    private function warmTree(): CategoryTree
    {
        $tree = $this->snapshot()->forStore(self::STORE_ID);
        self::assertNotSame('', (string) $this->service(CacheInterface::class)->load(self::CACHE_KEY));

        return $tree;
    }

    /**
     * Get the category tree port through the container
     *
     * @return CategoryTreeSnapshotInterface
     */
    private function snapshot(): CategoryTreeSnapshotInterface
    {
        return $this->service(CategoryTreeSnapshotInterface::class);
    }
}
