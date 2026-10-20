<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\CategoryTreeInvalidatorInterface;
use Hryvinskyi\DataLayer\Test\Integration\ResolvesServices;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;

/**
 * Builds catalogue data for the reader tests: products, categories, category assignments and parent relations
 *
 * Products and categories are saved through their resource models; assignments and relations are written straight
 * to their tables. Every test runs inside a rolled-back transaction.
 */
trait CatalogFixtures
{
    use ResolvesServices;

    /**
     * Drop every stored category tree, in this process and in the cache, so no test reads a tree built from another
     * test's rolled-back data
     *
     * @return void
     */
    private function cleanCategoryTrees(): void
    {
        $this->service(CategoryTreeInvalidatorInterface::class)->invalidate();
    }

    /**
     * Create an enabled simple product in the main website
     *
     * @param string $sku
     * @param string|null $name
     * @return int The product id
     */
    private function createProduct(string $sku, ?string $name = null): int
    {
        $product = $this->fresh(Product::class);
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setSku($sku)
            ->setName($name ?? $sku)
            ->setPrice(10)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setWebsiteIds([1]);
        $this->service(ProductResource::class)->save($product);

        return (int) $product->getId();
    }

    /**
     * Create a category below another one
     *
     * @param string $name
     * @param int $parentId
     * @param bool $active
     * @return int The category id
     */
    private function createCategory(string $name, int $parentId, bool $active = true): int
    {
        $resource = $this->service(CategoryResource::class);
        $parent = $this->fresh(Category::class);
        $resource->load($parent, $parentId);

        $category = $this->fresh(Category::class);
        $category->setStoreId(0)
            ->setName($name)
            ->setParentId($parentId)
            ->setPath((string) $parent->getPath())
            ->setPosition(1)
            ->setIsActive($active)
            ->setIncludeInMenu(true)
            ->setAvailableSortBy('name')
            ->setDefaultSortBy('name');
        $resource->save($category);
        $categoryId = $category->getId();

        return is_numeric($categoryId) ? (int) $categoryId : 0;
    }

    /**
     * Assign a product to categories
     *
     * @param int $productId
     * @param list<int> $categoryIds
     * @return void
     */
    private function assign(int $productId, array $categoryIds): void
    {
        $resource = $this->service(ResourceConnection::class);
        $resource->getConnection()->insertMultiple(
            $resource->getTableName('catalog_category_product'),
            array_map(
                static fn (int $categoryId): array => [
                    'category_id' => $categoryId,
                    'product_id' => $productId,
                    'position' => 0,
                ],
                $categoryIds
            )
        );
    }

    /**
     * Make one product the parent of another, as a configurable product is of its variants
     *
     * @param int $parentId
     * @param int $childId
     * @return void
     */
    private function relate(int $parentId, int $childId): void
    {
        $resource = $this->service(ResourceConnection::class);
        $connection = $resource->getConnection();
        $linkField = $this->service(MetadataPool::class)->getMetadata(ProductInterface::class)->getLinkField();
        $parentLink = $connection->fetchOne(
            $connection->select()
                ->from($resource->getTableName('catalog_product_entity'), [$linkField])
                ->where('entity_id = ?', $parentId)
        );
        $connection->insert(
            $resource->getTableName('catalog_product_relation'),
            ['parent_id' => is_numeric($parentLink) ? (int) $parentLink : 0, 'child_id' => $childId]
        );
    }
}
