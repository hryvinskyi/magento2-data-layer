<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\LineProductLoaderInterface;
use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

/**
 * Loads the products of many lines in one product collection, with the attributes their items report
 *
 * The collection is not limited to enabled or visible products: an order line reports the product it was sold as,
 * whatever has happened to it in the catalogue since.
 */
class LineProductLoader implements LineProductLoaderInterface
{
    private const PRODUCT_ID = 'entity_id';

    private const ALWAYS_LOADED = ['name', 'url_key'];

    /**
     * @param CollectionFactory $collectionFactory
     * @param ConfigInterface $config
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function byIds(array $productIds, int $storeId): array
    {
        $productIds = array_values(array_unique(array_filter($productIds, static fn (int $id): bool => $id > 0)));
        if ($productIds === []) {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addIdFilter($productIds);
        $collection->addAttributeToSelect($this->attributes($storeId));

        $products = [];
        foreach ($collection->getItems() as $product) {
            if ($product instanceof ProductInterface) {
                $products[(int) $product->getId()] = $product;
            }
        }

        return $products;
    }

    /**
     * Get the attribute codes an item reads from its product
     *
     * @param int $storeId
     * @return list<string>
     */
    private function attributes(int $storeId): array
    {
        $codes = self::ALWAYS_LOADED;
        $codes[] = $this->config->getIdentifierAttribute($storeId);
        $codes[] = (string) $this->config->getBrandAttribute($storeId);

        return array_values(array_unique(array_filter(
            $codes,
            static fn (string $code): bool => $code !== '' && $code !== self::PRODUCT_ID
        )));
    }
}
