<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\ItemField;

use Hryvinskyi\DataLayer\Model\ItemField\ListProvider;
use Hryvinskyi\DataLayer\Model\ItemField\QuantityProvider;
use PHPUnit\Framework\TestCase;

/**
 * Quantity comes from the subject (a whole number as an integer); list parameters only when the item is in a list
 */
class QuantityAndListProviderTest extends TestCase
{
    use SubjectDoubles;

    /**
     * A whole quantity is an integer, any other stays a number
     *
     * @return void
     */
    public function testQuantity(): void
    {
        self::assertSame(
            [0 => ['quantity' => 3], 1 => ['quantity' => 1.5]],
            (new QuantityProvider())->provide(
                [
                    $this->subject($this->product(1), ['quantity' => 3.0]),
                    $this->subject($this->product(2), ['quantity' => 1.5]),
                ],
                'add_to_cart'
            )
        );
    }

    /**
     * An item in a list reports the list and its position (the first position too); any other item nothing
     *
     * @return void
     */
    public function testListParameters(): void
    {
        self::assertSame(
            [0 => ['item_list_id' => 'category_5', 'item_list_name' => 'Laptops', 'index' => 0]],
            (new ListProvider())->provide(
                [
                    $this->subject(
                        $this->product(1),
                        ['listId' => 'category_5', 'listName' => 'Laptops', 'index' => 0]
                    ),
                    $this->subject($this->product(2)),
                ],
                'view_item_list'
            )
        );
    }
}
