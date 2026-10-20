<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Data;

use Hryvinskyi\DataLayer\Model\Data\ItemSubject;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use PHPUnit\Framework\TestCase;

/**
 * An item subject comes from at most one line and gives back what it was built from
 */
class ItemSubjectTest extends TestCase
{
    /**
     * A subject built from a cart line gives back every part
     *
     * @return void
     */
    public function testSubjectGivesBackWhatItWasBuiltFrom(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $child = $this->createMock(ProductInterface::class);
        $line = $this->createMock(CartItemInterface::class);

        $subject = new ItemSubject($product, 3, 2.0, $child, $line, null, 'category_7', 'Laptops', 4);

        self::assertSame($product, $subject->getProduct());
        self::assertSame($child, $subject->getChildProduct());
        self::assertSame($line, $subject->getQuoteItem());
        self::assertNull($subject->getOrderItem());
        self::assertSame(2.0, $subject->getQuantity());
        self::assertSame('category_7', $subject->getListId());
        self::assertSame('Laptops', $subject->getListName());
        self::assertSame(4, $subject->getIndex());
        self::assertSame(3, $subject->getStoreId());
    }

    /**
     * A catalogue product alone is a subject of quantity one outside any list
     *
     * @return void
     */
    public function testCatalogueSubjectDefaults(): void
    {
        $subject = new ItemSubject($this->createMock(ProductInterface::class), 1);

        self::assertSame(1.0, $subject->getQuantity());
        self::assertNull($subject->getChildProduct());
        self::assertNull($subject->getQuoteItem());
        self::assertNull($subject->getOrderItem());
        self::assertNull($subject->getListId());
        self::assertNull($subject->getListName());
        self::assertNull($subject->getIndex());
    }

    /**
     * A subject cannot come from a cart line and an order line at once
     *
     * @return void
     */
    public function testCartLineAndOrderLineTogetherAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ItemSubject(
            $this->createMock(ProductInterface::class),
            1,
            1.0,
            null,
            $this->createMock(CartItemInterface::class),
            $this->createMock(OrderItemInterface::class)
        );
    }
}
