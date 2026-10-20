<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use Hryvinskyi\DataLayer\Model\Cart\CartEventNaming;
use Hryvinskyi\DataLayer\Model\Event\CartEventBuilder;
use Hryvinskyi\DataLayer\Model\Event\EcommerceEventAssembler;
use Hryvinskyi\DataLayer\Model\Event\ItemsValue;
use Hryvinskyi\DataLayerApi\Api\CartTrackingPolicyInterface;
use Magento\Catalog\Model\Product;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * A cart event reports one item per visible line with the line's quantity, the cart's currency, the items' value and
 * coupon
 */
class CartEventBuilderTest extends TestCase
{
    use EventDoubles;

    /**
     * Every visible line makes a subject; a configurable line carries its only child's product
     *
     * @return void
     */
    public function testCartReportsItsLinesAndTotals(): void
    {
        $child = $this->product(31);
        $cart = $this->cart(
            [$this->line($this->product(3), 2.0, [$child]), $this->line($this->product(4), 1.0)],
            ['quote_currency_code' => 'GBP', 'subtotal' => '999', 'coupon_code' => 'SPRING']
        );
        $items = new FakeItemBuilder([3 => 10.0, 4 => 25.678]);

        $event = $this->builder('begin_checkout', $this->enabled(), $items)->build($cart);

        self::assertNotNull($event);
        self::assertSame('begin_checkout', $event->getName());
        self::assertSame(['currency' => 'GBP', 'value' => 45.68, 'coupon' => 'SPRING'], $event->getParameters());
        $subjects = $items->calls[0]['subjects'];
        self::assertCount(2, $subjects);
        self::assertSame(2.0, $subjects[0]->getQuantity());
        self::assertSame($child, $subjects[0]->getChildProduct());
        self::assertNull($subjects[1]->getChildProduct());
        self::assertNotNull($subjects[0]->getQuoteItem());
        self::assertSame(5, $subjects[0]->getStoreId());
    }

    /**
     * A line with several children (a bundle's selections) names no single child
     *
     * @return void
     */
    public function testSeveralChildrenNameNoChild(): void
    {
        $cart = $this->cart([$this->line($this->product(3), 1.0, [$this->product(31), $this->product(32)])]);
        $items = new FakeItemBuilder();

        $this->builder('view_cart', $this->enabled(), $items)->build($cart);

        self::assertNull($items->calls[0]['subjects'][0]->getChildProduct());
    }

    /**
     * Disabled event, untracked cart, empty cart and a cart that is not a shopping cart model make nothing
     *
     * @return void
     */
    public function testNothingIsBuilt(): void
    {
        $cart = $this->cart([$this->line($this->product(3), 1.0)]);
        $items = new FakeItemBuilder();

        $disabled = $this->enabled(['hryvinskyi_datalayer/events/disabled' => 'view_cart,purchase']);
        self::assertNull($this->builder('view_cart', $disabled, $items)->build($cart));
        self::assertNull($this->builder('view_cart', $this->enabled(), $items, false)->build($cart));
        self::assertNull($this->builder('view_cart', $this->enabled(), $items)->build($this->cart([])));
        self::assertNull(
            $this->builder('view_cart', $this->enabled(), $items)->build($this->createMock(CartInterface::class))
        );
        self::assertSame([], $items->calls);

        self::assertNull($this->builder('view_cart', $this->enabled(), new FakeItemBuilder([], true))->build($cart));
    }

    /**
     * A hidden price leaves out `value` by default, and skips the event when the store says so
     *
     * @return void
     */
    public function testHiddenPrice(): void
    {
        $cart = $this->cart(
            [$this->line($this->product(3), 1.0), $this->line($this->product(4), 1.0)],
            ['quote_currency_code' => 'GBP', 'subtotal' => 20]
        );

        $omitted = $this->builder('view_cart', $this->enabled(), new FakeItemBuilder(), true, [4])->build($cart);
        self::assertNotNull($omitted);
        self::assertSame(['currency' => 'GBP'], $omitted->getParameters());

        $skip = $this->enabled(['hryvinskyi_datalayer/item/hidden_price_handling' => 'skip_event']);
        self::assertNull($this->builder('view_cart', $skip, new FakeItemBuilder(), true, [4])->build($cart));
    }

    /**
     * The builder
     *
     * @param string $eventName
     * @param array<string, mixed> $settings
     * @param FakeItemBuilder $items
     * @param bool $tracked Whether the cart tracking policy tracks the cart
     * @param list<int> $hiddenProductIds
     * @return CartEventBuilder
     */
    private function builder(
        string $eventName,
        array $settings,
        FakeItemBuilder $items,
        bool $tracked = true,
        array $hiddenProductIds = []
    ): CartEventBuilder {
        $tracking = $this->createMock(CartTrackingPolicyInterface::class);
        $tracking->method('isTracked')->willReturn($tracked);

        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($this->createMock(Store::class));

        return new CartEventBuilder(
            $eventName,
            new EcommerceEventAssembler(
                $this->availability($settings),
                $tracking,
                $this->hiddenPricePolicy($settings, $hiddenProductIds),
                $items,
                $this->subjectFactory(),
                $this->eventFactory(),
                $stores,
                new ItemsValue(),
                new CartEventNaming()
            )
        );
    }

    /**
     * A cart of store view 5 with the given visible lines and stored data
     *
     * @param list<QuoteItem> $lines
     * @param array<string, mixed> $data
     * @return Quote
     */
    private function cart(array $lines, array $data = []): Quote
    {
        $cart = $this->createMock(Quote::class);
        $cart->method('getStoreId')->willReturn(5);
        $cart->method('getAllVisibleItems')->willReturn($lines);
        $cart->method('getData')->willReturnCallback(
            static fn (string $key = ''): mixed => $data[$key] ?? null
        );

        return $cart;
    }

    /**
     * A visible cart line
     *
     * @param Product $product
     * @param float $quantity
     * @param list<Product> $childProducts The products of its child lines
     * @return QuoteItem
     */
    private function line(Product $product, float $quantity, array $childProducts = []): QuoteItem
    {
        $children = [];
        foreach ($childProducts as $childProduct) {
            $child = $this->createMock(QuoteItem::class);
            $child->method('getProduct')->willReturn($childProduct);
            $children[] = $child;
        }
        $line = $this->createMock(QuoteItem::class);
        $line->method('getProduct')->willReturn($product);
        $line->method('getQty')->willReturn($quantity);
        $line->method('getChildren')->willReturn($children);

        return $line;
    }
}
