<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use Hryvinskyi\DataLayer\Model\Event\TransactionMoney;
use Hryvinskyi\DataLayer\Test\Unit\Model\ItemField\SubjectDoubles;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;

/**
 * `value` follows the store's basis and tax deduction; `tax` and `shipping` follow their toggles; all rounded
 */
class TransactionMoneyTest extends TestCase
{
    use SubjectDoubles;

    /**
     * The parameters for each combination of settings
     *
     * @dataProvider settingsTable
     * @param array<string, mixed> $settings
     * @param array<string, float> $expected
     * @return void
     */
    public function testParametersFollowTheStoreSettings(array $settings, array $expected): void
    {
        $money = new TransactionMoney($this->settings($settings));

        self::assertSame($expected, $money->parameters(123.456, 90.0, 20.004, 13.4, 1));
    }

    /**
     * Settings → parameters, for a grand total of 123.456, a subtotal of 90, tax 20.004 and shipping 13.4
     *
     * @return array<string, array{array<string, mixed>, array<string, float>}>
     */
    public static function settingsTable(): array
    {
        $both = [
            'hryvinskyi_datalayer/purchase/include_tax' => '1',
            'hryvinskyi_datalayer/purchase/include_shipping' => '1',
        ];

        return [
            'grand total by default, nothing else' => [[], ['value' => 123.46]],
            'grand total with tax and shipping' => [$both, ['value' => 123.46, 'tax' => 20.0, 'shipping' => 13.4]],
            'grand total less tax' => [
                ['hryvinskyi_datalayer/purchase/deduct_tax' => '1'],
                ['value' => 103.45],
            ],
            'subtotal ignores the tax deduction' => [
                [
                    'hryvinskyi_datalayer/purchase/value_basis' => 'subtotal',
                    'hryvinskyi_datalayer/purchase/deduct_tax' => '1',
                    'hryvinskyi_datalayer/purchase/include_shipping' => '1',
                ],
                ['value' => 90.0, 'shipping' => 13.4],
            ],
            'unknown basis reads as grand total' => [
                ['hryvinskyi_datalayer/purchase/value_basis' => 'net'],
                ['value' => 123.46],
            ],
        ];
    }

    /**
     * The subtotal basis takes the discount off, whatever sign it is stored with; the grand total already has it off
     *
     * @return void
     */
    public function testDiscountComesOffTheSubtotalOnly(): void
    {
        $subtotal = new TransactionMoney(
            $this->settings(['hryvinskyi_datalayer/purchase/value_basis' => 'subtotal'])
        );
        $grandTotal = new TransactionMoney($this->settings([]));

        self::assertSame(['value' => 80.0], $subtotal->parameters(110.0, 90.0, 10.0, 10.0, 1, -10.0));
        self::assertSame(['value' => 80.0], $subtotal->parameters(110.0, 90.0, 10.0, 10.0, 1, 10.0));
        self::assertSame(['value' => 0.0], $subtotal->parameters(10.0, 5.0, 0.0, 5.0, 1, -8.0));
        self::assertSame(['value' => 110.0], $grandTotal->parameters(110.0, 90.0, 10.0, 10.0, 1, -10.0));
    }

    /**
     * An order is read with its own totals and store view; its discount counts without the part that was tax
     *
     * @return void
     */
    public function testOrderMoney(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getGrandTotal')->willReturn(110.0);
        $order->method('getSubtotal')->willReturn(100.0);
        $order->method('getTaxAmount')->willReturn(10.0);
        $order->method('getShippingAmount')->willReturn(5.0);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getDiscountAmount')->willReturn(-12.0);
        $order->method('getDiscountTaxCompensationAmount')->willReturn(2.0);

        $money = new TransactionMoney($this->settings(['hryvinskyi_datalayer/purchase/value_basis' => 'subtotal']));

        self::assertSame(['value' => 90.0], $money->forOrder($order));
    }

    /**
     * On the subtotal basis only the items' discount comes off: a discount of 4 on shipping (0.5 of it tax) is left
     * out of an order discount of 16 (1.5 of it tax), so the subtotal of 100 loses 12 − 1
     *
     * @return void
     */
    public function testShippingDiscountStaysOffTheSubtotal(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getGrandTotal')->willReturn(106.0);
        $order->method('getSubtotal')->willReturn(100.0);
        $order->method('getTaxAmount')->willReturn(10.0);
        $order->method('getShippingAmount')->willReturn(12.0);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getDiscountAmount')->willReturn(-16.0);
        $order->method('getShippingDiscountAmount')->willReturn(4.0);
        $order->method('getDiscountTaxCompensationAmount')->willReturn(1.5);
        $order->method('getShippingDiscountTaxCompensationAmount')->willReturn(0.5);

        $money = new TransactionMoney($this->settings(['hryvinskyi_datalayer/purchase/value_basis' => 'subtotal']));

        self::assertSame(['value' => 89.0], $money->forOrder($order));
    }
}
