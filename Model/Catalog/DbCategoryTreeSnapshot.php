<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\CategoryTreeSnapshotInterface;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Catalog\Model\Category;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Reads a store view's active category tree from the database in one query
 *
 * Only categories below the store's root catalog are read. A category's name and active flag come from the store
 * view's value when it has one, else from the default value.
 */
class DbCategoryTreeSnapshot implements CategoryTreeSnapshotInterface
{
    /**
     * @param ResourceConnection $resource
     * @param CatalogLinkFields $linkFields
     * @param EavConfig $eavConfig
     * @param StoreManagerInterface $storeManager
     * @param CategoryTreeFactory $treeFactory
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CatalogLinkFields $linkFields,
        private readonly EavConfig $eavConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryTreeFactory $treeFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function forStore(int $storeId): CategoryTree
    {
        $store = $this->storeManager->getStore($storeId);
        $rootCategoryId = (int) $this->storeManager
            ->getGroup((string) $store->getStoreGroupId())
            ->getRootCategoryId();
        if ($rootCategoryId <= 0) {
            return $this->treeFactory->create(['nodes' => []]);
        }

        $connection = $this->resource->getConnection();
        $nodes = [];
        foreach ($connection->fetchAll($this->select($connection, $rootCategoryId, (int) $store->getId())) as $row) {
            $node = is_array($row) ? $this->node($row) : null;
            if ($node !== null) {
                $nodes[$node['id']] = $node['node'];
            }
        }

        return $this->treeFactory->create(['nodes' => $nodes]);
    }

    /**
     * Build the query for the active categories below the root catalog
     *
     * @param AdapterInterface $connection
     * @param int $rootCategoryId
     * @param int $storeId
     * @return Select
     * @throws LocalizedException When the category metadata cannot be resolved
     */
    private function select(AdapterInterface $connection, int $rootCategoryId, int $storeId): Select
    {
        $select = $connection->select()->from(
            ['e' => $this->resource->getTableName('catalog_category_entity')],
            ['entity_id', 'path', 'level', 'position']
        );
        $activeValue = $this->joinStoreValue($select, $connection, 'is_active', $storeId);
        $nameValue = $this->joinStoreValue($select, $connection, 'name', $storeId);

        return $select
            ->columns(['name' => $nameValue])
            ->where('e.path LIKE ?', Category::TREE_ROOT_ID . '/' . $rootCategoryId . '/%')
            ->where($activeValue . ' = 1');
    }

    /**
     * Join a category attribute's default and store-view values; return the expression preferring the store's
     *
     * @param Select $select
     * @param AdapterInterface $connection
     * @param string $attributeCode
     * @param int $storeId
     * @return string
     * @throws LocalizedException When the attribute or the category metadata cannot be resolved
     */
    private function joinStoreValue(
        Select $select,
        AdapterInterface $connection,
        string $attributeCode,
        int $storeId
    ): string {
        $attribute = $this->eavConfig->getAttribute(CategoryAttributeInterface::ENTITY_TYPE_CODE, $attributeCode);
        $condition = '%1$s.%2$s = e.%2$s AND %1$s.attribute_id = %3$d AND %1$s.store_id = %4$d';
        $linkField = $this->linkFields->category();
        $attributeId = (int) $attribute->getAttributeId();
        $default = $attributeCode . '_default';
        $store = $attributeCode . '_store';

        $select->joinLeft(
            [$default => $attribute->getBackendTable()],
            sprintf($condition, $default, $linkField, $attributeId, 0),
            []
        );
        $select->joinLeft(
            [$store => $attribute->getBackendTable()],
            sprintf($condition, $store, $linkField, $attributeId, $storeId),
            []
        );

        return (string) $connection->getCheckSql(
            $store . '.value_id IS NOT NULL',
            $store . '.value',
            $default . '.value'
        );
    }

    /**
     * Turn one row into a tree node; a row that is not a category below the root catalog is skipped
     *
     * @param array<mixed> $row
     * @return array{id: int, node: array{path: list<int>, level: int, position: int, name: string}}|null
     */
    private function node(array $row): ?array
    {
        $categoryId = is_numeric($row['entity_id'] ?? null) ? (int) $row['entity_id'] : 0;
        $path = is_string($row['path'] ?? null) ? $row['path'] : '';
        $pathIds = array_map('intval', array_slice(explode('/', $path), 2));
        if ($categoryId <= 0
            || $pathIds === []
            || min($pathIds) <= 0
            || $pathIds[count($pathIds) - 1] !== $categoryId
        ) {
            return null;
        }

        return [
            'id' => $categoryId,
            'node' => [
                'path' => $pathIds,
                'level' => is_numeric($row['level'] ?? null) ? (int) $row['level'] : 0,
                'position' => is_numeric($row['position'] ?? null) ? (int) $row['position'] : 0,
                'name' => is_scalar($row['name'] ?? null) ? trim((string) $row['name']) : '',
            ],
        ];
    }
}
