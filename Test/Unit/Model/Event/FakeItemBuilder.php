<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use Hryvinskyi\DataLayer\Model\Data\Item;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Hryvinskyi\DataLayerApi\Api\ItemBuilderInterface;

/**
 * Builds one item per subject: `item_id` "P<product id>", the subject's quantity, and the price set for the product
 *
 * Records the subjects of every call, so a test can assert what a builder handed to the item pipeline.
 */
class FakeItemBuilder implements ItemBuilderInterface
{
    /**
     * @var list<array{subjects: list<ItemSubjectInterface>, event: string}>
     */
    public array $calls = [];

    /**
     * @param array<int, float> $prices Product id to item price; a product without one reports no price
     * @param bool $buildsNothing True to build no item at all
     */
    public function __construct(
        private readonly array $prices = [],
        private readonly bool $buildsNothing = false
    ) {
    }

    /**
     * @inheritDoc
     */
    public function build(array $subjects, string $eventName): array
    {
        $this->calls[] = ['subjects' => $subjects, 'event' => $eventName];
        if ($this->buildsNothing) {
            return [];
        }

        $items = [];
        foreach ($subjects as $subject) {
            $productId = (int) $subject->getProduct()->getId();
            $parameters = ['item_id' => 'P' . $productId, 'quantity' => $subject->getQuantity()];
            if (isset($this->prices[$productId])) {
                $parameters['price'] = $this->prices[$productId];
            }
            $items[] = new Item($parameters);
        }

        return $items;
    }
}
