<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use PHPUnit\Framework\TestCase;

/**
 * One subject per visible order line, every product loaded in one call, a stand-in for a product that is gone
 */
class OrderItemSubjectsTest extends TestCase
{
    use OrderDoubles;

    /**
     * Visible lines make subjects in order; a configurable line carries its only child's product
     *
     * @return void
     */
    public function testVisibleLinesWithTheirChild(): void
    {
        $parent = $this->orderItem(1, 100, 2.0);
        $child = $this->orderItem(2, 101, 2.0, 1);
        $single = $this->orderItem(3, 200, 1.0);

        $subjects = $this->orderItemSubjects([100, 101, 200])->subjects([$parent, $child, $single], 3);

        self::assertSame([[100, 101, 200]], $this->loadedProductIds);
        self::assertCount(2, $subjects);
        self::assertSame(100, (int) $subjects[0]->getProduct()->getId());
        self::assertSame(101, (int) $subjects[0]->getChildProduct()?->getId());
        self::assertSame($parent, $subjects[0]->getOrderItem());
        self::assertSame(2.0, $subjects[0]->getQuantity());
        self::assertSame(3, $subjects[0]->getStoreId());
        self::assertSame(200, (int) $subjects[1]->getProduct()->getId());
        self::assertNull($subjects[1]->getChildProduct());
    }

    /**
     * A line whose product is gone from the catalogue reports a stand-in made from the line
     *
     * @return void
     */
    public function testGoneProductIsStoodInFor(): void
    {
        $subjects = $this->orderItemSubjects([])->subjects([$this->orderItem(1, 300, 1.0)], 3);

        $product = $subjects[0]->getProduct();
        self::assertSame(300, $product->getId());
        self::assertSame('SKU-300', $product->getSku());
        self::assertSame('Line 300', $product->getName());
        self::assertSame('simple', $product->getTypeId());
    }

    /**
     * An order without visible lines loads nothing
     *
     * @return void
     */
    public function testNoVisibleLine(): void
    {
        self::assertSame([], $this->orderItemSubjects([1])->subjects([], 3));
        self::assertSame([], $this->loadedProductIds);
    }
}
