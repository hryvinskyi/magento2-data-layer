<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\CategoryPathReaderInterface;
use Hryvinskyi\DataLayer\Api\Catalog\CategoryTreeSnapshotInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

/**
 * Reads the category path of many products with a fixed number of queries, whatever the number of products
 *
 * One query reads the category assignments of every product. A product with no assignment to an active category of
 * the store's catalog (typically a variant that is not shown on its own) takes the assignments of its parent
 * products, read for all such products in one more query. The category names come from the store's category tree,
 * which is cached. The category each product reports is picked by the tree's rule: the preferred category when it is
 * on the product's paths, else the deepest assigned one.
 */
class CategoryPathReader implements CategoryPathReaderInterface
{
    /**
     * @param ResourceConnection $resource
     * @param CatalogLinkFields $linkFields
     * @param CategoryTreeSnapshotInterface $trees
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CatalogLinkFields $linkFields,
        private readonly CategoryTreeSnapshotInterface $trees
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException When the store view or the catalogue metadata cannot be resolved
     */
    public function read(array $productIds, int $storeId, array $preferredCategoryIds = []): array
    {
        $productIds = array_values(array_unique(array_filter($productIds, static fn (int $id): bool => $id > 0)));
        if ($productIds === []) {
            return [];
        }

        $tree = $this->trees->forStore($storeId);
        $assignments = $this->assignments($productIds);
        $withoutCategory = array_values(array_filter(
            $productIds,
            static fn (int $productId): bool => $tree->choose($assignments[$productId] ?? []) === null
        ));
        if ($withoutCategory !== []) {
            $assignments = $this->parentAssignments($withoutCategory) + $assignments;
        }

        $paths = [];
        foreach ($productIds as $productId) {
            $categoryId = $tree->choose($assignments[$productId] ?? [], $preferredCategoryIds[$productId] ?? null);
            $names = $categoryId === null ? [] : $tree->pathNames($categoryId);
            if ($names !== []) {
                $paths[$productId] = $names;
            }
        }

        return $paths;
    }

    /**
     * Read the categories each product is assigned to
     *
     * @param list<int> $productIds
     * @return array<int, list<int>> Product id to category ids
     */
    private function assignments(array $productIds): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('catalog_category_product'), ['product_id', 'category_id'])
            ->where('product_id IN (?)', $productIds);

        return $this->grouped($connection->fetchAll($select));
    }

    /**
     * Read the categories the parent products of each product are assigned to
     *
     * The relation table names a parent by its link column, so the parent's id is read from the product table.
     *
     * @param list<int> $productIds
     * @return array<int, list<int>> Product id to the category ids of its parents
     * @throws LocalizedException When the product metadata cannot be resolved
     */
    private function parentAssignments(array $productIds): array
    {
        $linkField = $this->linkFields->product();
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                ['relation' => $this->resource->getTableName('catalog_product_relation')],
                ['product_id' => 'child_id']
            )
            ->join(
                ['parent' => $this->resource->getTableName('catalog_product_entity')],
                sprintf('parent.%s = relation.parent_id', $linkField),
                []
            )
            ->join(
                ['assignment' => $this->resource->getTableName('catalog_category_product')],
                'assignment.product_id = parent.entity_id',
                ['category_id']
            )
            ->where('relation.child_id IN (?)', $productIds);

        return $this->grouped($connection->fetchAll($select));
    }

    /**
     * Group product-to-category rows by product
     *
     * @param array<mixed> $rows
     * @return array<int, list<int>>
     */
    private function grouped(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            if (!is_array($row)
                || !is_numeric($row['product_id'] ?? null)
                || !is_numeric($row['category_id'] ?? null)
            ) {
                continue;
            }
            $grouped[(int) $row['product_id']][] = (int) $row['category_id'];
        }

        return $grouped;
    }
}
