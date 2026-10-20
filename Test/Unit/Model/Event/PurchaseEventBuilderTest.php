<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use Hryvinskyi\DataLayer\Model\Event\PurchaseEventBuilder;
use Hryvinskyi\DataLayer\Model\Event\TransactionMoney;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * `purchase` reports the order's id, store, currency, money on the store's basis, coupon and visible lines
 */
class PurchaseEventBuilderTest extends TestCase
{
    use OrderDoubles;

    private const ALL_MONEY = [
        'hryvinskyi_datalayer/purchase/include_tax' => '1',
        'hryvinskyi_datalayer/purchase/include_shipping' => '1',
    ];

    /**
     * Every parameter, read from the order and the settings of the order's store view
     *
     * @return void
     */
    public function testParameters(): void
    {
        $order = $this->order(
            [$this->orderItem(1, 100, 2.0), $this->orderItem(2, 200, 1.0)],
            ['coupon' => ' WINTER ', 'grand' => 150.004]
        );
        $items = new FakeItemBuilder([100 => 40.0, 200 => 20.0]);

        $event = $this->builder($this->enabled(self::ALL_MONEY), $items)->build($order);

        self::assertNotNull($event);
        self::assertSame('purchase', $event->getName());
        self::assertTrue($event->isEcommerce());
        self::assertSame(
            [
                'transaction_id' => '000000042',
                'affiliation' => 'Danish store',
                'currency' => 'DKK',
                'value' => 150.0,
                'tax' => 30.0,
                'shipping' => 20.0,
                'coupon' => 'WINTER',
            ],
            $event->getParameters()
        );
        self::assertCount(2, $event->getItems());
        self::assertSame([[100, 200]], $this->loadedProductIds, 'every line product in one load');
    }

    /**
     * The value basis and the toggles come from the order's store view, not the current one; the subtotal basis is
     * net of the order's discount
     *
     * @return void
     */
    public function testValueBasisAndToggles(): void
    {
        $order = $this->order([$this->orderItem(1, 100)]);
        $settings = $this->enabled([
            'hryvinskyi_datalayer/purchase/value_basis' => 'subtotal',
            'hryvinskyi_datalayer/purchase/include_shipping' => '1',
        ]);

        $event = $this->builder($settings, new FakeItemBuilder())->build($order);

        self::assertNotNull($event);
        self::assertSame(100.0, $event->getParameters()['value']);
        self::assertArrayNotHasKey('tax', $event->getParameters());
        self::assertSame(20.0, $event->getParameters()['shipping']);

        $deducted = $this->builder(
            $this->enabled(['hryvinskyi_datalayer/purchase/deduct_tax' => '1']),
            new FakeItemBuilder()
        )->build($order);
        self::assertSame(120.0, $deducted?->getParameters()['value']);

        $discounted = $this->order([$this->orderItem(1, 100)], ['discount' => -12.5, 'discount_tax' => 2.5]);
        $net = $this->builder($settings, new FakeItemBuilder())->build($discounted);
        self::assertSame(90.0, $net?->getParameters()['value'], 'the subtotal less the discount excluding tax');
    }

    /**
     * An order is a business record: its money stays whatever the store says about prices hidden from a visitor,
     * including when it says to skip such events
     *
     * @return void
     */
    public function testOrderKeepsItsMoneyWhateverTheHiddenPriceSetting(): void
    {
        $order = $this->order([$this->orderItem(1, 100)]);
        $settings = $this->enabled(self::ALL_MONEY + [
            'hryvinskyi_datalayer/item/hidden_price_handling' => 'skip_event',
        ]);

        $event = $this->builder($settings, new FakeItemBuilder())->build($order);

        self::assertSame(['value' => 150.0, 'tax' => 30.0, 'shipping' => 20.0], array_intersect_key(
            $event?->getParameters() ?? [],
            ['value' => true, 'tax' => true, 'shipping' => true]
        ));
    }

    /**
     * Disabled for the order's store, no visible line, or no item built: no event and no product load
     *
     * @return void
     */
    public function testNothingIsBuilt(): void
    {
        $order = $this->order([$this->orderItem(1, 100)]);
        $disabled = $this->enabled(['hryvinskyi_datalayer/events/disabled' => 'purchase']);

        self::assertNull($this->builder($disabled, new FakeItemBuilder())->build($order));
        self::assertSame([], $this->loadedProductIds);
        self::assertNull($this->builder($this->enabled(), new FakeItemBuilder())->build($this->order([])));
        self::assertNull($this->builder($this->enabled(), new FakeItemBuilder([], true))->build($order));
    }

    /**
     * A store view that no longer exists reports no affiliation
     *
     * @return void
     */
    public function testMissingStoreHasNoAffiliation(): void
    {
        $order = $this->order([$this->orderItem(1, 100)], ['store' => 99]);

        $event = $this->builder($this->enabled(), new FakeItemBuilder())->build($order);

        self::assertArrayNotHasKey('affiliation', $event?->getParameters() ?? []);
    }

    /**
     * The builder; store view 3 is called "Danish store", store view 99 does not exist
     *
     * @param array<string, mixed> $settings
     * @param FakeItemBuilder $items
     * @return PurchaseEventBuilder
     */
    private function builder(array $settings, FakeItemBuilder $items): PurchaseEventBuilder
    {
        $store = $this->createMock(Store::class);
        $store->method('getFrontendName')->willReturn(' Danish store ');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(
            static fn (int $storeId): Store => $storeId === 3
                ? $store
                : throw new NoSuchEntityException()
        );

        return new PurchaseEventBuilder(
            $this->availability($settings),
            $this->orderItemSubjects([100, 200]),
            $items,
            new TransactionMoney($this->settings($settings)),
            $this->eventFactory(),
            $storeManager
        );
    }
}
