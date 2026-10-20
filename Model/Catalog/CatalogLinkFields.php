<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Catalog;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Exception\LocalizedException;

/**
 * Names the column that catalogue attribute values and product relations are linked by
 *
 * The column is the entity id on an edition without content versioning and the row id on one with it; a category
 * or product id is never compared with a row id without going through the entity table.
 */
class CatalogLinkFields
{
    /**
     * @param MetadataPool $metadataPool
     */
    public function __construct(private readonly MetadataPool $metadataPool)
    {
    }

    /**
     * Get the column category values are linked by
     *
     * @return string
     * @throws LocalizedException When the category metadata is not registered
     */
    public function category(): string
    {
        return $this->linkField(CategoryInterface::class);
    }

    /**
     * Get the column product values and product relations are linked by
     *
     * @return string
     * @throws LocalizedException When the product metadata is not registered
     */
    public function product(): string
    {
        return $this->linkField(ProductInterface::class);
    }

    /**
     * Get an entity's link column
     *
     * @param class-string $entityType
     * @return string
     * @throws LocalizedException When the entity's metadata is not registered
     */
    private function linkField(string $entityType): string
    {
        try {
            return $this->metadataPool->getMetadata($entityType)->getLinkField();
        } catch (LocalizedException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new LocalizedException(
                __('The link field of %1 cannot be resolved.', $entityType),
                $exception
            );
        }
    }
}
