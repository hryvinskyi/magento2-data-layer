<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Data;

use Hryvinskyi\DataLayer\Model\Data\Item;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An item exists only when it is identified and every parameter has a GA4 name and a scalar value
 */
class ItemTest extends TestCase
{
    /**
     * An item identified by its id or its name keeps its parameters as given
     *
     * @dataProvider validItems
     * @param array<string,string|int|float> $parameters
     * @return void
     */
    #[DataProvider('validItems')]
    public function testValidItemKeepsItsParameters(array $parameters): void
    {
        self::assertSame($parameters, (new Item($parameters))->getParameters());
    }

    /**
     * An item without identity, with a bad parameter name or a value GA4 cannot take is refused
     *
     * @dataProvider invalidItems
     * @param array<mixed> $parameters
     * @return void
     */
    #[DataProvider('invalidItems')]
    public function testInvalidItemIsRefused(array $parameters): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Item($parameters);
    }

    /**
     * Items that satisfy every rule
     *
     * @return array<string, array{array<string, string|int|float>}>
     */
    public static function validItems(): array
    {
        return [
            'id only' => [['item_id' => 'SKU-1']],
            'name only' => [['item_name' => 'Laptop']],
            'numeric id' => [['item_id' => 42, 'price' => 10.5, 'quantity' => 2]],
            'custom parameter' => [['item_id' => 'SKU-1', 'custom_label_1' => 'Yes', 'item_category5' => 'Cables']],
            'longest name' => [['item_id' => 'SKU-1', 'a' . str_repeat('b', 39) => 'x']],
        ];
    }

    /**
     * Items that break one rule each
     *
     * @return array<string, array{array<mixed>}>
     */
    public static function invalidItems(): array
    {
        return [
            'no identity' => [['price' => 10.0]],
            'blank id and name' => [['item_id' => '  ', 'item_name' => '']],
            'name starts with a digit' => [['item_id' => 'SKU-1', '1price' => 1]],
            'upper-case name' => [['item_id' => 'SKU-1', 'Price' => 1]],
            'name too long' => [['item_id' => 'SKU-1', 'a' . str_repeat('b', 40) => 'x']],
            'name with a trailing newline' => [['item_id' => 'SKU-1', "price\n" => 1]],
            'numeric key' => [['item_id' => 'SKU-1', 0 => 'x']],
            'array value' => [['item_id' => 'SKU-1', 'item_variant' => ['red']]],
            'null value' => [['item_id' => 'SKU-1', 'coupon' => null]],
            'boolean value' => [['item_id' => 'SKU-1', 'in_stock' => true]],
            'infinite number' => [['item_id' => 'SKU-1', 'price' => INF]],
        ];
    }
}
