<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\ItemField;

use Hryvinskyi\DataLayer\Model\ItemField\CategoryProvider;
use PHPUnit\Framework\TestCase;

/**
 * Category names along one path, top level first, read once for every product of the event
 */
class CategoryProviderTest extends TestCase
{
    use SubjectDoubles;

    /**
     * One read for all products; the path fills item_category … item_category5, never more
     *
     * @return void
     */
    public function testOneReadFillsUpToFiveLevels(): void
    {
        $reader = new FakeCategoryPathReader([
            1 => ['Computers', 'Laptops'],
            2 => ['A', 'B', 'C', 'D', 'E', 'F'],
        ]);

        $fields = (new CategoryProvider($reader))->provide(
            [
                $this->subject($this->product(1)),
                $this->subject($this->product(2)),
                $this->subject($this->product(3)),
            ],
            'view_item_list'
        );

        self::assertSame(
            [
                0 => ['item_category' => 'Computers', 'item_category2' => 'Laptops'],
                1 => [
                    'item_category' => 'A',
                    'item_category2' => 'B',
                    'item_category3' => 'C',
                    'item_category4' => 'D',
                    'item_category5' => 'E',
                ],
            ],
            $fields
        );
        self::assertCount(1, $reader->calls);
        self::assertSame([1, 2, 3], $reader->calls[0]['productIds']);
    }

    /**
     * An item shown in a category's list prefers that category for its product
     *
     * @return void
     */
    public function testCategoryListIsPreferred(): void
    {
        $reader = new FakeCategoryPathReader();

        (new CategoryProvider($reader))->provide(
            [
                $this->subject($this->product(1), ['listId' => 'category_42', 'store' => 3]),
                $this->subject($this->product(2), ['listId' => 'related', 'store' => 3]),
            ],
            'view_item_list'
        );

        self::assertSame([['productIds' => [1, 2], 'storeId' => 3, 'preferred' => [1 => 42]]], $reader->calls);
    }
}
