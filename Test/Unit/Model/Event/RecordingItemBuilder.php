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
 * Builds one item per subject with a fixed unit price and the subject's quantity, and records what it was asked
 */
class RecordingItemBuilder implements ItemBuilderInterface
{
    /**
     * @var list<array{list<ItemSubjectInterface>, string}>
     */
    public array $calls = [];

    /**
     * @param float|null $price The unit price every item reports; null for items without a price
     */
    public function __construct(private readonly ?float $price = 25.0)
    {
    }

    /**
     * @inheritDoc
     */
    public function build(array $subjects, string $eventName): array
    {
        $this->calls[] = [$subjects, $eventName];
        $items = [];
        foreach ($subjects as $subject) {
            $parameters = ['item_id' => 'SKU-' . (string) $subject->getProduct()->getId()];
            if ($this->price !== null) {
                $parameters['price'] = $this->price;
            }
            $parameters['quantity'] = $subject->getQuantity();
            $items[] = new Item($parameters);
        }

        return $items;
    }
}
