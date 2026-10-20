<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\CategoryPathReaderInterface;
use Hryvinskyi\DataLayer\Model\Catalog\CategoryPathReader;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\TestCase;

/**
 * Category paths read from the real catalogue: the deepest or preferred category, active categories only, a
 * variant's parent categories, store-view names, five levels at most, and a query count that does not grow with the
 * number of products
 *
 * The fixture's tree below the default root catalog: Category 1 (3) > Category 1.1 (4) > Category 1.1.1 (5);
 * Category 2 (6, position 2); Movable (7, position 3); Inactive (8, not active).
 *
 * @magentoDbIsolation enabled
 * @magentoDataFixture Magento/Catalog/_files/categories_no_products.php
 */
class CategoryPathReaderTest extends TestCase
{
    use CatalogFixtures;

    private const STORE_ID = 1;

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
     * The reader port resolves to the batch reader
     *
     * @return void
     */
    public function testPortResolvesToTheBatchReader(): void
    {
        self::assertInstanceOf(CategoryPathReader::class, $this->reader());
    }

    /**
     * The deepest assigned category is reported, top level first; equal depths go to the lower position
     *
     * @return void
     */
    public function testDeepestCategoryWins(): void
    {
        $deep = $this->createProduct('dl-path-deep');
        $this->assign($deep, [3, 5, 6]);
        $level = $this->createProduct('dl-path-level');
        $this->assign($level, [7, 6]);

        self::assertSame(
            [
                $deep => ['Category 1', 'Category 1.1', 'Category 1.1.1'],
                $level => ['Category 2'],
            ],
            $this->reader()->read([$deep, $level], self::STORE_ID)
        );
    }

    /**
     * The preferred category wins when it is assigned or on the path of an assigned one, and only then
     *
     * @return void
     */
    public function testPreferredCategoryWinsWhenOnThePath(): void
    {
        $onPath = $this->createProduct('dl-path-on-path');
        $this->assign($onPath, [5]);
        $assigned = $this->createProduct('dl-path-assigned');
        $this->assign($assigned, [5, 6]);
        $elsewhere = $this->createProduct('dl-path-elsewhere');
        $this->assign($elsewhere, [5]);

        self::assertSame(
            [
                $onPath => ['Category 1'],
                $assigned => ['Category 2'],
                $elsewhere => ['Category 1', 'Category 1.1', 'Category 1.1.1'],
            ],
            $this->reader()->read(
                [$onPath, $assigned, $elsewhere],
                self::STORE_ID,
                [$onPath => 3, $assigned => 6, $elsewhere => 6]
            )
        );
    }

    /**
     * Inactive categories, and active ones below them, are never reported
     *
     * @return void
     */
    public function testInactiveCategoriesAreSkipped(): void
    {
        $belowInactive = $this->createCategory('Below inactive', 8);
        $mixed = $this->createProduct('dl-path-mixed');
        $this->assign($mixed, [8, $belowInactive, 6]);
        $hidden = $this->createProduct('dl-path-hidden');
        $this->assign($hidden, [8, $belowInactive]);

        self::assertSame(
            [$mixed => ['Category 2']],
            $this->reader()->read([$mixed, $hidden], self::STORE_ID)
        );
    }

    /**
     * A variant without categories of its own reports its parent's
     *
     * @return void
     */
    public function testChildWithoutCategoriesUsesItsParents(): void
    {
        $parent = $this->createProduct('dl-path-parent');
        $this->assign($parent, [4]);
        $child = $this->createProduct('dl-path-child');
        $this->relate($parent, $child);
        $alone = $this->createProduct('dl-path-alone');

        self::assertSame(
            [$child => ['Category 1', 'Category 1.1']],
            $this->reader()->read([$child, $alone], self::STORE_ID)
        );
    }

    /**
     * A category's store-view name is reported over its default name
     *
     * @return void
     */
    public function testStoreViewNameWinsOverDefault(): void
    {
        $resource = $this->service(CategoryResource::class);
        $category = $this->fresh(Category::class);
        $category->setStoreId(self::STORE_ID);
        $resource->load($category, 3);
        $category->setName('Category One In Store');
        $resource->save($category);
        $product = $this->createProduct('dl-path-store-name');
        $this->assign($product, [4]);

        self::assertSame(
            [$product => ['Category One In Store', 'Category 1.1']],
            $this->reader()->read([$product], self::STORE_ID)
        );
    }

    /**
     * A path deeper than five levels reports its five top levels
     *
     * @return void
     */
    public function testAtMostFiveNamesAreReported(): void
    {
        $level4 = $this->createCategory('Level 4', 5);
        $level5 = $this->createCategory('Level 5', $level4);
        $level6 = $this->createCategory('Level 6', $level5);
        $product = $this->createProduct('dl-path-deepest');
        $this->assign($product, [$level6]);

        self::assertSame(
            [$product => ['Category 1', 'Category 1.1', 'Category 1.1.1', 'Level 4', 'Level 5']],
            $this->reader()->read([$product], self::STORE_ID)
        );
    }

    /**
     * Reading twenty products issues as many queries as reading two
     *
     * Half the products are assigned to a category; the other half are variants reporting their parent's, so both
     * the assignment query and the parent query run in each read.
     *
     * @return void
     */
    public function testQueryCountDoesNotGrowWithTheNumberOfProducts(): void
    {
        $parent = $this->createProduct('dl-path-count-parent');
        $this->assign($parent, [4]);
        $productIds = [];
        for ($index = 0; $index < 20; $index++) {
            $productId = $this->createProduct('dl-path-count-' . $index);
            $productIds[] = $productId;
            if ($index % 2 === 0) {
                $this->assign($productId, [6]);
                continue;
            }
            $this->relate($parent, $productId);
        }
        $reader = $this->reader();
        $reader->read($productIds, self::STORE_ID);

        $forTwo = $this->countQueries(fn (): array => $reader->read(array_slice($productIds, 0, 2), self::STORE_ID));
        $paths = [];
        $forTwenty = $this->countQueries(function () use ($reader, $productIds, &$paths): array {
            $paths = $reader->read($productIds, self::STORE_ID);

            return $paths;
        });

        self::assertCount(20, $paths);
        self::assertSame(['Category 1', 'Category 1.1'], $paths[$productIds[1]]);
        self::assertSame(2, $forTwo, 'one assignment query and one parent query, the tree coming from the cache');
        self::assertSame($forTwo, $forTwenty);
    }

    /**
     * Get the reader through the container
     *
     * @return CategoryPathReaderInterface
     */
    private function reader(): CategoryPathReaderInterface
    {
        return $this->service(CategoryPathReaderInterface::class);
    }

    /**
     * Count the database queries a read issues
     *
     * @param callable(): array<mixed> $read
     * @return int
     */
    private function countQueries(callable $read): int
    {
        $connection = $this->service(ResourceConnection::class)->getConnection();
        self::assertInstanceOf(\Zend_Db_Adapter_Abstract::class, $connection);
        $profiler = $connection->getProfiler();
        $profiler->setEnabled(true);
        $profiler->clear();
        try {
            $read();

            return $profiler->getTotalNumQueries();
        } finally {
            $profiler->setEnabled(false);
        }
    }
}
