<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use Hryvinskyi\DataLayer\Model\Data\Item;
use Hryvinskyi\DataLayer\Model\Event\ItemsValue;
use PHPUnit\Framework\TestCase;

/**
 * An event's value is taken from its items as reported: price less discount, per unit, times quantity
 */
class ItemsValueTest extends TestCase
{
    /**
     * Each item counts its price less its discount, times its quantity; the sum is rounded to two decimals
     *
     * @return void
     */
    public function testPriceLessDiscountTimesQuantity(): void
    {
        $value = (new ItemsValue())->value([
            new Item(['item_id' => 'A', 'price' => 40.0, 'discount' => 4.0, 'quantity' => 2]),
            new Item(['item_id' => 'B', 'price' => 10.004, 'quantity' => 1]),
            new Item(['item_id' => 'C', 'price' => 5, 'discount' => 0.5, 'quantity' => 1.5]),
        ]);

        self::assertSame(88.75, $value);
    }

    /**
     * An item without a price counts nothing, whatever its discount; no items are worth nothing
     *
     * @return void
     */
    public function testItemsWithoutAPriceCountNothing(): void
    {
        $values = new ItemsValue();

        self::assertSame(0.0, $values->value([new Item(['item_id' => 'A', 'discount' => 2.0, 'quantity' => 3])]));
        self::assertSame(0.0, $values->value([]));
    }

    /**
     * A discount larger than the price it is reported against never makes the value negative
     *
     * @return void
     */
    public function testValueIsNeverNegative(): void
    {
        $values = new ItemsValue();

        self::assertSame(0.0, $values->value([
            new Item(['item_id' => 'A', 'price' => 20.0, 'discount' => 30.0, 'quantity' => 2]),
        ]));
    }
}
