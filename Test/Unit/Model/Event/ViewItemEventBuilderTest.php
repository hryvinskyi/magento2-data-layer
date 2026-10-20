<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use Hryvinskyi\DataLayer\Model\Event\ViewItemEventBuilder;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * `view_item` reports the product as one item of quantity 1 with the store's currency and the item's price as value
 */
class ViewItemEventBuilderTest extends TestCase
{
    use EventDoubles;

    /**
     * A visible product reports currency, value and one item of quantity 1
     *
     * @return void
     */
    public function testVisibleProduct(): void
    {
        $items = new FakeItemBuilder([7 => 19.999]);

        $event = $this->builder($this->enabled(), $items)->build($this->product(7));

        self::assertNotNull($event);
        self::assertSame(
            ['event' => 'view_item', 'ecommerce' => [
                'currency' => 'EUR',
                'value' => 20.0,
                'items' => [['item_id' => 'P7', 'quantity' => 1.0, 'price' => 19.999]],
            ]],
            $event->toDataLayer()
        );
        self::assertSame(1, $items->calls[0]['subjects'][0]->getStoreId());
        self::assertSame(1.0, $items->calls[0]['subjects'][0]->getQuantity());
    }

    /**
     * A disabled event is never built: the item pipeline is not even asked
     *
     * @return void
     */
    public function testDisabledEventBuildsNothing(): void
    {
        $items = new FakeItemBuilder([7 => 10.0]);

        $disabled = $this->enabled(['hryvinskyi_datalayer/events/disabled' => 'view_item']);
        self::assertNull($this->builder($disabled, $items)->build($this->product(7)));
        self::assertNull($this->builder([], $items)->build($this->product(7)));
        self::assertSame([], $items->calls);
    }

    /**
     * A hidden price leaves out `value` by default, and skips the event when the store says so
     *
     * @return void
     */
    public function testHiddenPrice(): void
    {
        $omitted = $this->builder($this->enabled(), new FakeItemBuilder([7 => 10.0]), [7])->build($this->product(7));
        self::assertNotNull($omitted);
        self::assertSame(['currency' => 'EUR'], $omitted->getParameters());

        $skip = $this->enabled(['hryvinskyi_datalayer/item/hidden_price_handling' => 'skip_event']);
        self::assertNull($this->builder($skip, new FakeItemBuilder([7 => 10.0]), [7])->build($this->product(7)));
    }

    /**
     * A product that makes no item makes no event; a product without a price reports no value
     *
     * @return void
     */
    public function testNoItemOrNoPrice(): void
    {
        self::assertNull($this->builder($this->enabled(), new FakeItemBuilder([], true))->build($this->product(7)));

        $event = $this->builder($this->enabled(), new FakeItemBuilder())->build($this->product(7));
        self::assertNotNull($event);
        self::assertSame(['currency' => 'EUR'], $event->getParameters());
    }

    /**
     * The builder for store view 1, whose current currency is EUR
     *
     * @param array<string, mixed> $settings
     * @param FakeItemBuilder $items
     * @param list<int> $hiddenProductIds
     * @return ViewItemEventBuilder
     */
    private function builder(
        array $settings,
        FakeItemBuilder $items,
        array $hiddenProductIds = []
    ): ViewItemEventBuilder {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getCurrentCurrencyCode')->willReturn('EUR');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new ViewItemEventBuilder(
            $this->availability($settings),
            $this->hiddenPricePolicy($settings, $hiddenProductIds),
            $items,
            $this->subjectFactory(),
            $this->eventFactory(),
            $storeManager
        );
    }
}
