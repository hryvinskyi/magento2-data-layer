<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use Hryvinskyi\DataLayer\Api\Data\ConversionValueInterface;
use Hryvinskyi\DataLayer\Api\Data\ConversionValueInterfaceFactory;
use Hryvinskyi\DataLayer\Api\Event\ConversionValueSourceInterface;
use Hryvinskyi\DataLayer\Model\Data\ConversionValue;
use Hryvinskyi\DataLayer\Model\Event\AdsConversionEventBuilder;
use Hryvinskyi\DataLayer\Model\Event\ConversionValue\FixedValue;
use Hryvinskyi\DataLayer\Model\Event\ConversionValue\PurchaseValue;
use Hryvinskyi\DataLayer\Model\Event\TransactionMoney;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `ads_conversion` reports an order to Google Ads with its `send_to`, its transaction id and the value the store view
 * picks; it is never built where conversions are off, incomplete or disabled, or where `purchase` is not reported
 */
class AdsConversionEventBuilderTest extends TestCase
{
    use OrderDoubles;

    private const ADS = [
        'hryvinskyi_datalayer/ads/enabled' => '1',
        'hryvinskyi_datalayer/ads/conversion_id' => 'AW-123456',
        'hryvinskyi_datalayer/ads/conversion_label' => 'QgmJ_Lu-9',
    ];

    /**
     * By default the conversion carries the purchase value, on the store's purchase basis, in the order's currency
     *
     * @return void
     */
    public function testPurchaseValueIsTheDefault(): void
    {
        $event = $this->builder($this->enabled(self::ADS + [
            'hryvinskyi_datalayer/purchase/deduct_tax' => '1',
        ]))->build($this->order([], ['grand' => 150.0, 'tax' => 30.0, 'currency' => 'EUR']));

        self::assertNotNull($event);
        self::assertSame(
            [
                'event' => 'ads_conversion',
                'send_to' => 'AW-123456/QgmJ_Lu-9',
                'transaction_id' => '000000042',
                'value' => 120.0,
                'currency' => 'EUR',
            ],
            $event->toDataLayer()
        );
        self::assertFalse($event->isEcommerce());
    }

    /**
     * The fixed value source reports the store view's amount in the order's currency, whatever the order's total
     *
     * @return void
     */
    public function testFixedValue(): void
    {
        $event = $this->builder($this->enabled(self::ADS + [
            'hryvinskyi_datalayer/ads/value_source' => 'fixed',
            'hryvinskyi_datalayer/ads/fixed_value' => '25.555',
        ]))->build($this->order([], ['grand' => 999.0, 'currency' => 'DKK']));

        self::assertNotNull($event);
        self::assertSame(25.56, $event->getParameters()['value'] ?? null);
        self::assertSame('DKK', $event->getParameters()['currency'] ?? null);
    }

    /**
     * Without a fixed amount, or without an order currency, the conversion is reported without a value
     *
     * @return void
     */
    public function testWithoutAValueTheConversionCarriesNone(): void
    {
        $fixedWithoutAmount = $this->builder($this->enabled(self::ADS + [
            'hryvinskyi_datalayer/ads/value_source' => 'fixed',
        ]))->build($this->order([]));
        $noCurrency = $this->builder($this->enabled(self::ADS))->build($this->order([], ['currency' => '']));

        foreach ([$fixedWithoutAmount, $noCurrency] as $event) {
            self::assertNotNull($event);
            self::assertSame(['send_to', 'transaction_id'], array_keys($event->getParameters()));
        }
    }

    /**
     * Settings under which no conversion is built
     *
     * @param array<string, string> $settings
     * @param array{increment?: string} $order
     * @return void
     * @dataProvider noConversionProvider
     */
    #[DataProvider('noConversionProvider')]
    public function testNoConversion(array $settings, array $order): void
    {
        self::assertNull($this->builder($settings)->build($this->order([], $order)));
    }

    /**
     * Get settings and orders that report no conversion
     *
     * @return array<string, array{array<string, string>, array{increment?: string}}>
     */
    public static function noConversionProvider(): array
    {
        $on = ['hryvinskyi_datalayer/general/enabled' => '1'];

        return [
            'data layer off' => [self::ADS, []],
            'conversions off' => [$on + ['hryvinskyi_datalayer/ads/enabled' => '0'] + self::ADS, []],
            'no conversion id' => [$on + ['hryvinskyi_datalayer/ads/conversion_id' => ''] + self::ADS, []],
            'bare conversion number' => [$on + ['hryvinskyi_datalayer/ads/conversion_id' => '123'] + self::ADS, []],
            'no label' => [$on + ['hryvinskyi_datalayer/ads/conversion_label' => ''] + self::ADS, []],
            'event disabled' => [$on + ['hryvinskyi_datalayer/events/disabled' => 'ads_conversion'] + self::ADS, []],
            'purchase disabled' => [$on + ['hryvinskyi_datalayer/events/disabled' => 'purchase'] + self::ADS, []],
            'order without increment id' => [$on + self::ADS, ['increment' => ' ']],
        ];
    }

    /**
     * Every value source code needs a source, and only sources are accepted
     *
     * @return void
     */
    public function testValueSourcePoolMustCoverEverySource(): void
    {
        $source = $this->createMock(ConversionValueSourceInterface::class);
        try {
            new AdsConversionEventBuilder(
                $this->settings(),
                $this->availability([]),
                $this->eventFactory(),
                ['purchase' => $source]
            );
            self::fail('A pool without the fixed source was accepted.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('"fixed"', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        new AdsConversionEventBuilder(
            $this->settings(),
            $this->availability([]),
            $this->eventFactory(),
            ['purchase' => $source, 'fixed' => 'not a source']
        );
    }

    /**
     * A conversion value refuses an amount that is not money and a currency that is not a code, and rounds the rest
     *
     * @return void
     */
    public function testConversionValueIsValidByConstruction(): void
    {
        $value = new ConversionValue(10.005, 'EUR');
        self::assertSame(10.01, $value->getAmount());
        self::assertSame('EUR', $value->getCurrency());

        $refused = [[-0.01, 'EUR'], [INF, 'EUR'], [NAN, 'EUR'], [1.0, 'eur'], [1.0, 'EURO'], [1.0, "EUR\n"], [1.0, '']];
        foreach ($refused as $case) {
            try {
                new ConversionValue($case[0], $case[1]);
                self::fail(sprintf('%s %s was accepted.', (string) $case[0], $case[1]));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * Get the builder over the given settings, with the real value sources
     *
     * @param array<string, mixed> $settings
     * @return AdsConversionEventBuilder
     */
    private function builder(array $settings): AdsConversionEventBuilder
    {
        $config = $this->settings($settings);
        $values = $this->createMock(ConversionValueInterfaceFactory::class);
        $values->method('create')->willReturnCallback(
            static function (array $data): ConversionValueInterface {
                $amount = $data['amount'] ?? null;
                $currency = $data['currency'] ?? null;

                return new ConversionValue(
                    is_float($amount) ? $amount : -1.0,
                    is_string($currency) ? $currency : ''
                );
            }
        );

        return new AdsConversionEventBuilder(
            $config,
            $this->availability($settings),
            $this->eventFactory(),
            [
                'purchase' => new PurchaseValue(new TransactionMoney($config), $values),
                'fixed' => new FixedValue($config, $values),
            ]
        );
    }
}
