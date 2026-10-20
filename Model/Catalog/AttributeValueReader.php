<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\AttributeValueReaderInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Reads one product attribute's stored values for many products in one query
 *
 * A static attribute is read from its column of the product table. Any other attribute is read from its value table,
 * the store view's value preferred over the default one. Products without a value are left out. An attribute that
 * does not exist (or a static one with no column) yields nothing, and is logged once per call.
 */
class AttributeValueReader implements AttributeValueReaderInterface
{
    /**
     * @param ResourceConnection $resource
     * @param CatalogLinkFields $linkFields
     * @param EavConfig $eavConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CatalogLinkFields $linkFields,
        private readonly EavConfig $eavConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException When the product metadata cannot be resolved
     */
    public function read(array $productIds, string $attributeCode, int $storeId): array
    {
        $productIds = array_values(array_unique(array_filter($productIds, static fn (int $id): bool => $id > 0)));
        if ($productIds === []) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $select = $this->select($connection, $attributeCode, $storeId);
        if ($select === null) {
            $this->logger->warning(
                'The data layer cannot read the product attribute: it does not exist or has no stored value.',
                ['attribute_code' => $attributeCode]
            );

            return [];
        }

        $values = [];
        foreach ($connection->fetchPairs($select->where('e.entity_id IN (?)', $productIds)) as $productId => $value) {
            if (is_scalar($value) && $value !== '') {
                $values[(int) $productId] = (string) $value;
            }
        }

        return $values;
    }

    /**
     * Build the query reading product id to value, or null when the attribute has nowhere to read from
     *
     * @param AdapterInterface $connection
     * @param string $attributeCode
     * @param int $storeId
     * @return Select|null
     * @throws LocalizedException When the product metadata cannot be resolved
     */
    private function select(AdapterInterface $connection, string $attributeCode, int $storeId): ?Select
    {
        $attribute = $this->attribute($attributeCode);
        if ($attribute === null) {
            return null;
        }

        $productTable = $this->resource->getTableName('catalog_product_entity');
        $select = $connection->select()->from(['e' => $productTable], ['entity_id']);
        if ($attribute->isStatic()) {
            return $connection->tableColumnExists($productTable, $attributeCode)
                ? $select->columns(['value' => 'e.' . $attributeCode])
                : null;
        }

        $condition = '%1$s.%2$s = e.%2$s AND %1$s.attribute_id = %3$d AND %1$s.store_id = %4$d';
        $linkField = $this->linkFields->product();
        $attributeId = (int) $attribute->getAttributeId();

        return $select
            ->joinLeft(
                ['default_value' => $attribute->getBackendTable()],
                sprintf($condition, 'default_value', $linkField, $attributeId, 0),
                []
            )
            ->joinLeft(
                ['store_value' => $attribute->getBackendTable()],
                sprintf($condition, 'store_value', $linkField, $attributeId, $storeId),
                []
            )
            ->columns([
                'value' => $connection->getCheckSql(
                    'store_value.value_id IS NOT NULL',
                    'store_value.value',
                    'default_value.value'
                ),
            ]);
    }

    /**
     * Get the product attribute, or null when there is none with that code
     *
     * @param string $attributeCode
     * @return AbstractAttribute|null
     */
    private function attribute(string $attributeCode): ?AbstractAttribute
    {
        try {
            $attribute = $this->eavConfig->getAttribute(ProductAttributeInterface::ENTITY_TYPE_CODE, $attributeCode);
        } catch (LocalizedException) {
            return null;
        }

        return (int) $attribute->getAttributeId() > 0 ? $attribute : null;
    }
}
