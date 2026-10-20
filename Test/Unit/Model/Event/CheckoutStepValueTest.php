<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use Hryvinskyi\DataLayer\Api\Event\CartValueInterface;
use Hryvinskyi\DataLayer\Model\Config;
use Hryvinskyi\DataLayer\Model\Config\DraftSettings;
use Hryvinskyi\DataLayer\Model\Data\Item;
use Hryvinskyi\DataLayer\Model\Event\CartValue\GrandTotal;
use Hryvinskyi\DataLayer\Model\Event\CartValue\ItemsTotal;
use Hryvinskyi\DataLayer\Model\Event\CheckoutStepValue;
use Hryvinskyi\DataLayer\Model\Event\ItemsValue;
use Hryvinskyi\DataLayer\Test\Unit\Model\FakeScopeConfig;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;

/**
 * A checkout step's cart is worth its items' value by default, or its grand total when the cart's store view says so;
 * the pool of cart values must cover every basis
 */
class CheckoutStepValueTest extends TestCase
{
    /**
     * By default the step is worth its items' value: price less discount, times quantity, without shipping or tax
     *
     * @return void
     */
    public function testItemsValueByDefault(): void
    {
        $value = $this->stepValue([]);

        self::assertSame(45.0, $value->valueOf($this->cart(4, 61.5), $this->items()));
    }

    /**
     * A store view whose basis is the grand total reports the cart's grand total; the setting is read for the cart's
     * own store view
     *
     * @return void
     */
    public function testGrandTotalWhenTheStoreViewSaysSo(): void
    {
        $scopeConfig = new FakeScopeConfig(['hryvinskyi_datalayer/checkout/step_value_basis' => 'grand_total']);
        $value = new CheckoutStepValue(
            new Config($scopeConfig, new Json(), new DraftSettings()),
            ['items' => new ItemsTotal(new ItemsValue()), 'grand_total' => new GrandTotal()]
        );

        self::assertSame(61.5, $value->valueOf($this->cart(4, 61.499), $this->items()));
        self::assertSame([4], array_values(array_unique($scopeConfig->askedScopes)));
    }

    /**
     * An unknown stored basis reads as the items' value
     *
     * @return void
     */
    public function testUnknownBasisReadsAsItemsValue(): void
    {
        $value = $this->stepValue(['hryvinskyi_datalayer/checkout/step_value_basis' => 'subtotal']);

        self::assertSame(45.0, $value->valueOf($this->cart(1, 61.5), $this->items()));
    }

    /**
     * The grand total never goes below zero, and a cart that never collected its totals is worth zero
     *
     * @return void
     */
    public function testGrandTotalBounds(): void
    {
        $grandTotal = new GrandTotal();

        self::assertSame(0.0, $grandTotal->valueOf($this->cart(1, -3.0), []));
        self::assertSame(0.0, $grandTotal->valueOf($this->cart(1, null), []));
    }

    /**
     * A pool that misses a known basis, or holds something that is not a cart value, fails when it is built
     *
     * @return void
     */
    public function testIncompletePoolFailsWhenBuilt(): void
    {
        $config = new Config(new FakeScopeConfig(), new Json(), new DraftSettings());
        $items = new ItemsTotal(new ItemsValue());

        try {
            new CheckoutStepValue($config, ['items' => $items]);
            self::fail('A pool without the grand total was accepted.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('"grand_total"', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(CartValueInterface::class);
        new CheckoutStepValue($config, ['items' => $items, 'grand_total' => new \stdClass()]);
    }

    /**
     * The step value with both bases registered, reading the given settings
     *
     * @param array<string, string> $settings
     * @return CheckoutStepValue
     */
    private function stepValue(array $settings): CheckoutStepValue
    {
        return new CheckoutStepValue(
            new Config(new FakeScopeConfig($settings), new Json(), new DraftSettings()),
            ['items' => new ItemsTotal(new ItemsValue()), 'grand_total' => new GrandTotal()]
        );
    }

    /**
     * Two items: 2 × (20 − 2.5) and 1 × 10
     *
     * @return list<Item>
     */
    private function items(): array
    {
        return [
            new Item(['item_id' => 'A', 'price' => 20.0, 'discount' => 2.5, 'quantity' => 2]),
            new Item(['item_id' => 'B', 'price' => 10.0, 'quantity' => 1]),
        ];
    }

    /**
     * A cart of a store view with a stored grand total
     *
     * @param int $storeId
     * @param float|null $grandTotal Null for a cart whose totals were never collected
     * @return Quote
     */
    private function cart(int $storeId, ?float $grandTotal): Quote
    {
        $cart = $this->createMock(Quote::class);
        $cart->method('getStoreId')->willReturn($storeId);
        $cart->method('getData')->willReturnCallback(
            static fn (string $key = ''): mixed => $key === 'grand_total' ? $grandTotal : null
        );

        return $cart;
    }
}
