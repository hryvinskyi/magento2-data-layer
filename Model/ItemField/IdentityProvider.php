<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\ItemField;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\ItemField\ProductAttributeValuesInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Hryvinskyi\DataLayerApi\Api\ItemFieldProviderInterface;

/**
 * Fills `item_id` and `item_name`
 *
 * `item_id` is the catalogue product's value of the store's identifier attribute (the product id for `entity_id`);
 * values the products were not loaded with are read for all of them at once. `item_name` is the line's name for an
 * item that comes from a cart or order line, the product's name otherwise.
 */
class IdentityProvider implements ItemFieldProviderInterface
{
    /**
     * @param ConfigInterface $config
     * @param ProductAttributeValuesInterface $attributeValues
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ProductAttributeValuesInterface $attributeValues
    ) {
    }

    /**
     * @inheritDoc
     */
    public function provide(array $subjects, string $eventName): array
    {
        $productsByStore = [];
        foreach ($subjects as $subject) {
            $productId = (int) $subject->getProduct()->getId();
            if ($productId > 0) {
                $productsByStore[$subject->getStoreId()][$productId] = $subject->getProduct();
            }
        }

        $identifiers = [];
        foreach ($productsByStore as $storeId => $products) {
            $identifiers[$storeId] = $this->attributeValues->values(
                $products,
                $this->config->getIdentifierAttribute($storeId),
                $storeId
            );
        }

        $fields = [];
        foreach ($subjects as $key => $subject) {
            $itemFields = [];
            $identifier = $identifiers[$subject->getStoreId()][(int) $subject->getProduct()->getId()] ?? '';
            if ($identifier !== '') {
                $itemFields['item_id'] = $identifier;
            }
            $name = $this->name($subject);
            if ($name !== '') {
                $itemFields['item_name'] = $name;
            }
            if ($itemFields !== []) {
                $fields[$key] = $itemFields;
            }
        }

        return $fields;
    }

    /**
     * Get the name an item reports: its line's, or its product's when the line has none or there is no line
     *
     * @param ItemSubjectInterface $subject
     * @return string
     */
    private function name(ItemSubjectInterface $subject): string
    {
        $line = $subject->getOrderItem() ?? $subject->getQuoteItem();
        $name = trim((string) $line?->getName());

        return $name !== '' ? $name : trim((string) $subject->getProduct()->getName());
    }
}
