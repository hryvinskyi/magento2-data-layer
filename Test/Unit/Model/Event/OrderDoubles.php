<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use Hryvinskyi\DataLayer\Api\Catalog\LineProductLoaderInterface;
use Hryvinskyi\DataLayer\Model\Event\OrderItemSubjects;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Model\Product;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;

/**
 * Orders, order lines and the subjects built from them, as test doubles
 */
trait OrderDoubles
{
    use EventDoubles;

    /**
     * Product ids asked of the line product loader, one list per call
     *
     * @var list<list<int>>
     */
    private array $loadedProductIds = [];

    /**
     * An order of store view 3 with the given lines and data
     *
     * @param list<OrderItem> $lines
     * @param array{increment?: string, currency?: string, grand?: float, subtotal?: float, tax?: float,
     *     shipping?: float, coupon?: string|null, store?: int, discount?: float, discount_tax?: float} $data
     * @return Order
     */
    private function order(array $lines, array $data = []): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getItems')->willReturn($lines);
        $order->method('getStoreId')->willReturn($data['store'] ?? 3);
        $order->method('getEntityId')->willReturn(10);
        $order->method('getIncrementId')->willReturn($data['increment'] ?? '000000042');
        $order->method('getOrderCurrencyCode')->willReturn($data['currency'] ?? 'DKK');
        $order->method('getGrandTotal')->willReturn($data['grand'] ?? 150.0);
        $order->method('getSubtotal')->willReturn($data['subtotal'] ?? 100.0);
        $order->method('getTaxAmount')->willReturn($data['tax'] ?? 30.0);
        $order->method('getShippingAmount')->willReturn($data['shipping'] ?? 20.0);
        $order->method('getCouponCode')->willReturn($data['coupon'] ?? null);
        $order->method('getDiscountAmount')->willReturn($data['discount'] ?? 0.0);
        $order->method('getDiscountTaxCompensationAmount')->willReturn($data['discount_tax'] ?? 0.0);

        return $order;
    }

    /**
     * An order line
     *
     * @param int $itemId
     * @param int $productId
     * @param float $quantity
     * @param int|null $parentItemId
     * @return OrderItem
     */
    private function orderItem(int $itemId, int $productId, float $quantity = 1.0, ?int $parentItemId = null): OrderItem
    {
        $line = $this->createMock(OrderItem::class);
        $line->method('getItemId')->willReturn($itemId);
        $line->method('getProductId')->willReturn($productId);
        $line->method('getQtyOrdered')->willReturn($quantity);
        $line->method('getParentItemId')->willReturn($parentItemId);
        $line->method('getParentItem')->willReturn(null);
        $line->method('getSku')->willReturn('SKU-' . $productId);
        $line->method('getName')->willReturn('Line ' . $productId);
        $line->method('getProductType')->willReturn('simple');

        return $line;
    }

    /**
     * Order item subjects over a loader that knows the given products and records what it is asked
     *
     * @param list<int> $knownProductIds Products that still exist in the catalogue
     * @return OrderItemSubjects
     */
    private function orderItemSubjects(array $knownProductIds): OrderItemSubjects
    {
        $known = [];
        foreach ($knownProductIds as $productId) {
            $known[$productId] = $this->product($productId, 'Catalogue ' . $productId);
        }
        $loader = $this->createMock(LineProductLoaderInterface::class);
        $loader->method('byIds')->willReturnCallback(
            function (array $productIds) use ($known): array {
                $ids = array_values(array_map(static fn (mixed $id): int => is_int($id) ? $id : 0, $productIds));
                $this->loadedProductIds[] = $ids;

                return array_intersect_key($known, array_flip($ids));
            }
        );
        $productFactory = $this->createMock(ProductInterfaceFactory::class);
        $productFactory->method('create')->willReturnCallback(fn (): Product => $this->standIn());

        return new OrderItemSubjects($loader, $this->subjectFactory(), $productFactory);
    }

    /**
     * A product that keeps what is set on it
     *
     * @return Product
     */
    private function standIn(): Product
    {
        $data = [];
        $product = $this->createMock(Product::class);
        foreach (['Id' => 'id', 'Sku' => 'sku', 'Name' => 'name', 'TypeId' => 'type_id'] as $method => $key) {
            $product->method('set' . $method)->willReturnCallback(
                static function (mixed $value) use (&$data, $key, $product): Product {
                    $data[$key] = $value;

                    return $product;
                }
            );
            $product->method('get' . $method)->willReturnCallback(
                static function () use (&$data, $key): mixed {
                    return $data[$key] ?? null;
                }
            );
        }

        return $product;
    }
}
