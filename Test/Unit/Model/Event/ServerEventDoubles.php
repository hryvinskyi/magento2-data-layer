<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Model\Data\ItemSubject;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterfaceFactory;
use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Generated factories that build the real value objects, and carts, cart lines and products as test doubles
 */
trait ServerEventDoubles
{
    /**
     * An event factory that creates real events
     *
     * @return EventInterfaceFactory&MockObject
     */
    private function eventFactory(): EventInterfaceFactory
    {
        $factory = $this->createMock(EventInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            static function (array $data): Event {
                $parameters = $data['parameters'] ?? [];
                $items = $data['items'] ?? [];

                return new Event(
                    is_string($data['name'] ?? null) ? $data['name'] : '',
                    ($data['ecommerce'] ?? false) === true,
                    is_array($parameters) ? $parameters : [],
                    is_array($items) ? $items : []
                );
            }
        );

        return $factory;
    }

    /**
     * An item subject factory that creates real subjects
     *
     * @return ItemSubjectInterfaceFactory&MockObject
     */
    private function subjectFactory(): ItemSubjectInterfaceFactory
    {
        $factory = $this->createMock(ItemSubjectInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            static function (array $data): ItemSubject {
                $product = $data['product'] ?? null;
                if (!$product instanceof Product) {
                    throw new \InvalidArgumentException('A subject needs a product.');
                }
                $child = $data['childProduct'] ?? null;
                $line = $data['quoteItem'] ?? null;

                return new ItemSubject(
                    $product,
                    is_int($data['storeId'] ?? null) ? $data['storeId'] : 0,
                    is_float($data['quantity'] ?? null) ? $data['quantity'] : 1.0,
                    $child instanceof Product ? $child : null,
                    $line instanceof QuoteItem ? $line : null
                );
            }
        );

        return $factory;
    }

    /**
     * A catalogue product
     *
     * @param int $id
     * @return Product&MockObject
     */
    private function catalogueProduct(int $id): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);

        return $product;
    }

    /**
     * A cart of a store view, with the given stored data and visible lines
     *
     * @param array<string, mixed> $data
     * @param list<QuoteItem> $lines
     * @param int $storeId
     * @return Quote&MockObject
     */
    private function cart(array $data = [], array $lines = [], int $storeId = 1): Quote
    {
        $cart = $this->createMock(Quote::class);
        $cart->method('getStoreId')->willReturn($storeId);
        $cart->method('getId')->willReturn($data['entity_id'] ?? 7);
        $cart->method('getData')->willReturnCallback(
            static fn (string $key = ''): mixed => $data[$key] ?? null
        );
        $cart->method('getAllVisibleItems')->willReturn($lines);

        return $cart;
    }

    /**
     * A cart line of a product, in a cart
     *
     * @param Product $product
     * @param Quote $cart
     * @param float $quantity
     * @param list<QuoteItem> $children
     * @return QuoteItem&MockObject
     */
    private function line(Product $product, Quote $cart, float $quantity = 1.0, array $children = []): QuoteItem
    {
        $line = $this->createMock(QuoteItem::class);
        $line->method('getProduct')->willReturn($product);
        $line->method('getQuote')->willReturn($cart);
        $line->method('getQty')->willReturn($quantity);
        $line->method('getChildren')->willReturn($children);

        return $line;
    }

    /**
     * The event that was built, asserted to exist
     *
     * @param EventInterface|null $event
     * @return EventInterface
     */
    private function built(?EventInterface $event): EventInterface
    {
        self::assertNotNull($event);

        return $event;
    }
}
