<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\OptionLabelReaderInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/**
 * Reads the store labels of a product attribute's options in one query
 *
 * Only options of the named attribute are read. An option's label is its store view label when it has one, else its
 * default (admin) label; an option with neither is left out, and so is every option of an attribute that does not
 * exist.
 */
class OptionLabelReader implements OptionLabelReaderInterface
{
    /**
     * @param ResourceConnection $resource
     * @param EavConfig $eavConfig
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function labels(string $attributeCode, array $optionIds, int $storeId): array
    {
        $optionIds = $this->optionIds($optionIds);
        $attributeId = $this->attributeId($attributeCode);
        if ($optionIds === [] || $attributeId === null) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $valueTable = $this->resource->getTableName('eav_attribute_option_value');
        $select = $connection->select()
            ->from(['o' => $this->resource->getTableName('eav_attribute_option')], ['option_id'])
            ->joinLeft(
                ['default_label' => $valueTable],
                'default_label.option_id = o.option_id AND default_label.store_id = 0',
                []
            )
            ->joinLeft(
                ['store_label' => $valueTable],
                $connection->quoteInto('store_label.option_id = o.option_id AND store_label.store_id = ?', $storeId),
                []
            )
            ->columns([
                'label' => $connection->getCheckSql(
                    'store_label.value_id IS NOT NULL',
                    'store_label.value',
                    'default_label.value'
                ),
            ])
            ->where('o.attribute_id = ?', $attributeId)
            ->where('o.option_id IN (?)', $optionIds);

        $labels = [];
        foreach ($connection->fetchPairs($select) as $optionId => $label) {
            if (is_scalar($label) && (string) $label !== '') {
                $labels[(int) $optionId] = (string) $label;
            }
        }

        return $labels;
    }

    /**
     * Keep the positive whole-number option ids
     *
     * @param array<int|string> $optionIds
     * @return list<int>
     */
    private function optionIds(array $optionIds): array
    {
        $accepted = [];
        foreach ($optionIds as $optionId) {
            if (is_int($optionId) || ctype_digit($optionId)) {
                $accepted[] = (int) $optionId;
            }
        }

        return array_values(array_unique(array_filter($accepted, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Get the product attribute's id, or null when there is none with that code
     *
     * @param string $attributeCode
     * @return int|null
     */
    private function attributeId(string $attributeCode): ?int
    {
        try {
            $attributeId = (int) $this->eavConfig
                ->getAttribute(ProductAttributeInterface::ENTITY_TYPE_CODE, $attributeCode)
                ->getAttributeId();
        } catch (LocalizedException) {
            return null;
        }

        return $attributeId > 0 ? $attributeId : null;
    }
}
