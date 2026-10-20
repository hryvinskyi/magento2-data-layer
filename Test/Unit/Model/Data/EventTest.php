<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Data;

use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Model\Data\Item;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An event exists only with a GA4 name, GA4 parameter names, scalar parameter values and real items, and it gives
 * the object the browser pushes to the data layer
 */
class EventTest extends TestCase
{
    /**
     * An ecommerce event puts its parameters and its items' parameters under `ecommerce`
     *
     * @return void
     */
    public function testEcommerceEventWithItemsNestsParametersAndItems(): void
    {
        $event = new Event(
            'add_to_cart',
            true,
            ['currency' => 'GBP', 'value' => 21.0],
            [new Item(['item_id' => 'A', 'price' => 10.5]), new Item(['item_id' => 'B', 'quantity' => 1])]
        );

        self::assertSame(
            [
                'event' => 'add_to_cart',
                'ecommerce' => [
                    'currency' => 'GBP',
                    'value' => 21.0,
                    'items' => [['item_id' => 'A', 'price' => 10.5], ['item_id' => 'B', 'quantity' => 1]],
                ],
            ],
            $event->toDataLayer()
        );
    }

    /**
     * An ecommerce event without items has no `items` key
     *
     * @return void
     */
    public function testEcommerceEventWithoutItemsHasNoItemsKey(): void
    {
        $event = new Event('view_item_list', true, ['item_list_id' => 'category_3']);

        self::assertSame(
            ['event' => 'view_item_list', 'ecommerce' => ['item_list_id' => 'category_3']],
            $event->toDataLayer()
        );
    }

    /**
     * Any other event carries its parameters at the top level
     *
     * @return void
     */
    public function testOtherEventCarriesParametersAtTheTopLevel(): void
    {
        $event = new Event('login', false, ['method' => 'password', 'returning' => true]);

        self::assertSame(['event' => 'login', 'method' => 'password', 'returning' => true], $event->toDataLayer());
        self::assertSame('login', $event->getName());
        self::assertFalse($event->isEcommerce());
        self::assertSame([], $event->getItems());
    }

    /**
     * The items keep their order and come back as a list
     *
     * @return void
     */
    public function testItemsComeBackAsAListInOrder(): void
    {
        $first = new Item(['item_id' => 'A']);
        $second = new Item(['item_id' => 'B']);

        $event = new Event('view_cart', true, [], ['x' => $first, 'y' => $second]);

        self::assertSame([$first, $second], $event->getItems());
    }

    /**
     * An event breaking any rule is refused
     *
     * @dataProvider invalidEvents
     * @param string $name
     * @param array<mixed> $parameters
     * @param array<mixed> $items
     * @return void
     */
    #[DataProvider('invalidEvents')]
    public function testInvalidEventIsRefused(string $name, array $parameters, array $items): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Event($name, true, $parameters, $items);
    }

    /**
     * Events that break one rule each
     *
     * @return array<string, array{string, array<mixed>, array<mixed>}>
     */
    public static function invalidEvents(): array
    {
        return [
            'empty name' => ['', [], []],
            'upper-case name' => ['AddToCart', [], []],
            'name with a hyphen' => ['add-to-cart', [], []],
            'name too long' => ['a' . str_repeat('b', 40), [], []],
            'name with a trailing newline' => ["purchase\n", [], []],
            'parameter name with a trailing newline' => ['purchase', ["value\n" => 1], []],
            'bad parameter name' => ['purchase', ['Value' => 1], []],
            'numeric parameter key' => ['purchase', [0 => 1], []],
            'reserved parameter event' => ['purchase', ['event' => 'x'], []],
            'reserved parameter items' => ['purchase', ['items' => 'x'], []],
            'reserved parameter ecommerce' => ['purchase', ['ecommerce' => 'x'], []],
            'array value' => ['purchase', ['value' => [1]], []],
            'null value' => ['purchase', ['coupon' => null], []],
            'not-a-number value' => ['purchase', ['value' => NAN], []],
            'item that is not an item' => ['purchase', [], [['item_id' => 'A']]],
        ];
    }

    /**
     * A name of forty characters starting with a letter is accepted
     *
     * @return void
     */
    public function testLongestNameIsAccepted(): void
    {
        $name = 'a' . str_repeat('_', 39);

        self::assertSame($name, (new Event($name, false))->getName());
    }
}
