<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Catalog;

use Hryvinskyi\DataLayer\Model\Catalog\CategoryTree;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The category a product reports, and the names along its path
 */
class CategoryTreeTest extends TestCase
{
    /**
     * Names run from the top level down to the category
     *
     * @return void
     */
    public function testPathNamesRunFromTheTopLevelDown(): void
    {
        self::assertSame(['Computers', 'Laptops', 'Gaming'], $this->tree()->pathNames(12));
        self::assertSame([10, 11, 12], $this->tree()->pathIds(12));
    }

    /**
     * A path deeper than five levels reports its five top levels
     *
     * @return void
     */
    public function testAtMostFiveNamesAreReported(): void
    {
        self::assertSame(['L1', 'L2', 'L3', 'L4', 'L5'], $this->tree()->pathNames(45));
    }

    /**
     * An unknown category and an active category below an inactive one are not reported
     *
     * @return void
     */
    public function testUnknownAndUnreachableCategoriesAreNotReported(): void
    {
        $tree = $this->tree();

        self::assertSame([], $tree->pathNames(999));
        self::assertFalse($tree->contains(999));
        self::assertSame([], $tree->pathNames(31));
        self::assertSame([], $tree->pathIds(31));
        self::assertFalse($tree->contains(31));
        self::assertTrue($tree->contains(21));
    }

    /**
     * A category without a name in the store view is left out of the names
     *
     * @return void
     */
    public function testUnnamedCategoriesAreLeftOutOfTheNames(): void
    {
        self::assertSame(['Named'], $this->tree()->pathNames(51));
    }

    /**
     * The category a product reports, for each rule
     *
     * @param list<int> $assigned
     * @param int|null $preferred
     * @param int|null $expected
     * @return void
     */
    #[DataProvider('choices')]
    public function testChoosesTheReportedCategory(array $assigned, ?int $preferred, ?int $expected): void
    {
        self::assertSame($expected, $this->tree()->choose($assigned, $preferred));
    }

    /**
     * Assigned categories, the preferred one, and the category reported
     *
     * @return array<string, array{list<int>, int|null, int|null}>
     */
    public static function choices(): array
    {
        return [
            'deepest wins' => [[10, 12, 20], null, 12],
            'same depth goes to the lower position' => [[21, 22], null, 22],
            'same depth and position go to the lower id' => [[23, 22], null, 22],
            'preferred on the path of an assigned category wins' => [[12], 10, 10],
            'preferred and assigned wins over a deeper one' => [[12, 21], 21, 21],
            'preferred off every path is ignored' => [[12], 20, 12],
            'preferred that is not reported is ignored' => [[12], 30, 12],
            'categories that are not reported are skipped' => [[31, 30, 20], null, 20],
            'only unreported categories give nothing' => [[31, 999], null, null],
            'no category gives nothing' => [[], 10, null],
        ];
    }

    /**
     * The tree comes back unchanged from the shape it is stored in
     *
     * @return void
     */
    public function testRoundTripsThroughItsStoredShape(): void
    {
        $tree = $this->tree();

        self::assertEquals($tree, new CategoryTree($tree->toArray()));
    }

    /**
     * A malformed node is refused
     *
     * @param array<mixed> $nodes
     * @return void
     */
    #[DataProvider('malformedNodes')]
    public function testRefusesMalformedNodes(array $nodes): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CategoryTree($nodes);
    }

    /**
     * Nodes a tree refuses
     *
     * @return array<string, array{array<mixed>}>
     */
    public static function malformedNodes(): array
    {
        $valid = ['path' => [10], 'level' => 2, 'position' => 1, 'name' => 'Computers'];

        return [
            'key that is not an id' => [['ten' => $valid]],
            'non-positive key' => [[0 => ['path' => [0], 'level' => 2, 'position' => 1, 'name' => 'x']]],
            'path not ending with the category' => [[11 => $valid]],
            'empty path' => [[10 => ['path' => []] + $valid]],
            'path holding text' => [[10 => ['path' => ['10']] + $valid]],
            'level as text' => [[10 => ['level' => '2'] + $valid]],
            'missing name' => [[10 => ['path' => [10], 'level' => 2, 'position' => 1]]],
            'node that is not an array' => [[10 => 'Computers']],
        ];
    }

    /**
     * Build the tree the tests read
     *
     * Category 30 is inactive, so it is missing; 31 sits below it. 50 has no name in the store view.
     *
     * @return CategoryTree
     */
    private function tree(): CategoryTree
    {
        $nodes = [
            10 => ['path' => [10], 'level' => 2, 'position' => 1, 'name' => 'Computers'],
            11 => ['path' => [10, 11], 'level' => 3, 'position' => 1, 'name' => 'Laptops'],
            12 => ['path' => [10, 11, 12], 'level' => 4, 'position' => 1, 'name' => 'Gaming'],
            20 => ['path' => [20], 'level' => 2, 'position' => 2, 'name' => 'Phones'],
            21 => ['path' => [20, 21], 'level' => 3, 'position' => 2, 'name' => 'Cases'],
            22 => ['path' => [20, 22], 'level' => 3, 'position' => 1, 'name' => 'Chargers'],
            23 => ['path' => [20, 23], 'level' => 3, 'position' => 1, 'name' => 'Cables'],
            31 => ['path' => [30, 31], 'level' => 3, 'position' => 1, 'name' => 'Below inactive'],
            50 => ['path' => [50], 'level' => 2, 'position' => 9, 'name' => ''],
            51 => ['path' => [50, 51], 'level' => 3, 'position' => 1, 'name' => 'Named'],
        ];
        $path = [];
        foreach ([40, 41, 42, 43, 44, 45] as $depth => $categoryId) {
            $path[] = $categoryId;
            $nodes[$categoryId] = [
                'path' => $path,
                'level' => $depth + 2,
                'position' => 1,
                'name' => 'L' . ($depth + 1),
            ];
        }

        return new CategoryTree($nodes);
    }
}
