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
use Hryvinskyi\DataLayer\Model\EventAvailability;
use Hryvinskyi\DataLayer\Model\HiddenPricePolicy;
use Hryvinskyi\DataLayer\Test\Unit\Model\ItemField\SubjectDoubles;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\PriceVisibilityInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * The real value objects and policies behind event builders, created from settings and test doubles
 */
trait EventDoubles
{
    use SubjectDoubles;

    /**
     * Settings with the data layer enabled, merged with the given ones
     *
     * @param array<string, mixed> $values Keyed by configuration path
     * @return array<string, mixed>
     */
    private function enabled(array $values = []): array
    {
        return $values + ['hryvinskyi_datalayer/general/enabled' => '1'];
    }

    /**
     * Event availability read from the given settings
     *
     * @param array<string, mixed> $values
     * @return EventAvailability
     */
    private function availability(array $values): EventAvailability
    {
        return new EventAvailability($this->settings($values));
    }

    /**
     * The hidden-price policy over the given settings, with the given products' prices hidden from the viewer
     *
     * @param array<string, mixed> $values
     * @param list<int> $hiddenProductIds
     * @return HiddenPricePolicy
     */
    private function hiddenPricePolicy(array $values, array $hiddenProductIds = []): HiddenPricePolicy
    {
        $visibility = $this->createMock(PriceVisibilityInterface::class);
        $visibility->method('isPriceVisible')->willReturnCallback(
            static fn (ProductInterface $product): bool => !in_array((int) $product->getId(), $hiddenProductIds, true)
        );

        return new HiddenPricePolicy($visibility, $this->settings($values));
    }

    /**
     * The generated event factory, creating real events
     *
     * @return EventInterfaceFactory
     */
    private function eventFactory(): EventInterfaceFactory
    {
        $factory = $this->createMock(EventInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            static function (array $data): EventInterface {
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
     * The generated item subject factory, creating real subjects
     *
     * @return ItemSubjectInterfaceFactory
     */
    private function subjectFactory(): ItemSubjectInterfaceFactory
    {
        $factory = $this->createMock(ItemSubjectInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            static function (array $data): ItemSubjectInterface {
                $product = $data['product'] ?? null;
                if (!$product instanceof ProductInterface) {
                    throw new \InvalidArgumentException('A subject needs a product.');
                }
                $child = $data['childProduct'] ?? null;
                $quoteItem = $data['quoteItem'] ?? null;
                $orderItem = $data['orderItem'] ?? null;

                return new ItemSubject(
                    $product,
                    is_int($data['storeId'] ?? null) ? $data['storeId'] : 0,
                    is_float($data['quantity'] ?? null) ? $data['quantity'] : 1.0,
                    $child instanceof ProductInterface ? $child : null,
                    $quoteItem instanceof CartItemInterface ? $quoteItem : null,
                    $orderItem instanceof OrderItemInterface ? $orderItem : null
                );
            }
        );

        return $factory;
    }
}
