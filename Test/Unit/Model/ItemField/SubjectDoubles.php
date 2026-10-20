<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\ItemField;

use Hryvinskyi\DataLayer\Model\Config;
use Hryvinskyi\DataLayer\Model\Config\DraftSettings;
use Hryvinskyi\DataLayer\Model\Data\ItemSubject;
use Hryvinskyi\DataLayer\Test\Unit\Model\FakeScopeConfig;
use Magento\Catalog\Model\Product;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Products, cart lines and order lines as test doubles, and item subjects built from them
 */
trait SubjectDoubles
{
    /**
     * A catalogue product loaded with the given data
     *
     * @param int $id
     * @param string $name
     * @param array<string, mixed> $data The attribute values the product was loaded with
     * @param string $typeId
     * @return Product&MockObject
     */
    private function product(int $id, string $name = 'Product', array $data = [], string $typeId = 'simple'): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getName')->willReturn($name);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('hasData')->willReturnCallback(
            static fn (string $key): bool => array_key_exists($key, $data)
        );
        $product->method('getData')->willReturnCallback(
            static fn (string $key = ''): mixed => $data[$key] ?? null
        );

        return $product;
    }

    /**
     * A cart line with the given stored data
     *
     * @param array<string, mixed> $data
     * @param string|null $couponCode The cart's coupon
     * @param bool $cartResolved False for a line that returns no cart
     * @return QuoteItem&MockObject
     */
    private function cartLine(array $data = [], ?string $couponCode = null, bool $cartResolved = true): QuoteItem
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getData')->willReturnCallback(
            static fn (string $key = ''): mixed => $key === 'coupon_code' ? $couponCode : null
        );
        $line = $this->createMock(QuoteItem::class);
        $line->method('getData')->willReturnCallback(
            static fn (string $key = ''): mixed => $data[$key] ?? null
        );
        $line->method('getName')->willReturn($data['name'] ?? null);
        $line->method('getQty')->willReturn($data['qty'] ?? null);
        $line->method('getProductType')->willReturn($data['product_type'] ?? 'simple');
        $line->method('getCalculationPrice')->willReturn($data['custom_price'] ?? $data['price'] ?? null);
        $line->method('getQuote')->willReturn($cartResolved ? $quote : null);

        return $line;
    }

    /**
     * An order line with the given stored data
     *
     * @param array<string, mixed> $data
     * @param string|null $couponCode The order's coupon
     * @param bool $orderResolved False for a line whose order cannot be resolved
     * @return OrderItem&MockObject
     */
    private function orderLine(array $data = [], ?string $couponCode = null, bool $orderResolved = true): OrderItem
    {
        $order = $this->createMock(Order::class);
        $order->method('getCouponCode')->willReturn($couponCode);
        $line = $this->createMock(OrderItem::class);
        $line->method('getName')->willReturn($data['name'] ?? null);
        $line->method('getPrice')->willReturn($data['price'] ?? null);
        $line->method('getPriceInclTax')->willReturn($data['price_incl_tax'] ?? null);
        $line->method('getDiscountAmount')->willReturn($data['discount_amount'] ?? null);
        $line->method('getDiscountTaxCompensationAmount')
            ->willReturn($data['discount_tax_compensation_amount'] ?? null);
        $line->method('getQtyOrdered')->willReturn($data['qty_ordered'] ?? null);
        $line->method('getTaxPercent')->willReturn($data['tax_percent'] ?? null);
        $line->method('getProductType')->willReturn($data['product_type'] ?? 'simple');
        $line->method('getProductOptions')->willReturn($data['product_options'] ?? []);
        $line->method('getOrder')->willReturn($orderResolved ? $order : null);

        return $line;
    }

    /**
     * An item subject
     *
     * @param Product $product
     * @param array{store?: int, quantity?: float, child?: Product, cart?: QuoteItem, order?: OrderItem,
     *     listId?: string, listName?: string, index?: int} $with
     * @return ItemSubject
     */
    private function subject(Product $product, array $with = []): ItemSubject
    {
        return new ItemSubject(
            $product,
            $with['store'] ?? 1,
            $with['quantity'] ?? 1.0,
            $with['child'] ?? null,
            $with['cart'] ?? null,
            $with['order'] ?? null,
            $with['listId'] ?? null,
            $with['listName'] ?? null,
            $with['index'] ?? null
        );
    }

    /**
     * The data layer's settings read from the given values
     *
     * @param array<string, mixed> $values Keyed by configuration path
     * @return Config
     */
    private function settings(array $values = []): Config
    {
        return new Config(
            new FakeScopeConfig($values),
            $this->createMock(SerializerInterface::class),
            new DraftSettings()
        );
    }
}
