<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\ItemField;

use Hryvinskyi\DataLayer\Api\Catalog\AttributeTextReaderInterface;
use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\ItemField\ProductAttributeValuesInterface;
use Hryvinskyi\DataLayerApi\Api\ItemFieldProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Fills `item_brand` with the text of the product's value of the store's brand attribute
 *
 * Nothing is done for a store view without a brand attribute. The values of all products are read at once (or taken
 * from the loaded products) and turned into text in one go: an option attribute gives its option labels, joined with
 * a comma, and a text attribute gives the text it holds.
 */
class BrandProvider implements ItemFieldProviderInterface
{
    /**
     * @param ConfigInterface $config
     * @param ProductAttributeValuesInterface $attributeValues
     * @param AttributeTextReaderInterface $attributeTexts
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ProductAttributeValuesInterface $attributeValues,
        private readonly AttributeTextReaderInterface $attributeTexts
    ) {
    }

    /**
     * @inheritDoc
     */
    public function provide(array $subjects, string $eventName): array
    {
        /** @var array<int, array<int, ProductInterface>> $productsByStore */
        $productsByStore = [];
        foreach ($subjects as $subject) {
            $productId = (int) $subject->getProduct()->getId();
            if ($productId > 0) {
                $productsByStore[$subject->getStoreId()][$productId] = $subject->getProduct();
            }
        }

        $brands = [];
        foreach ($productsByStore as $storeId => $products) {
            $attributeCode = $this->config->getBrandAttribute($storeId);
            if ($attributeCode !== null) {
                $brands[$storeId] = $this->brands($products, $attributeCode, $storeId);
            }
        }

        $fields = [];
        foreach ($subjects as $key => $subject) {
            $brand = $brands[$subject->getStoreId()][(int) $subject->getProduct()->getId()] ?? '';
            if ($brand !== '') {
                $fields[$key] = ['item_brand' => $brand];
            }
        }

        return $fields;
    }

    /**
     * Get the brand of each product of one store view
     *
     * @param array<int,ProductInterface> $products Keyed by product id
     * @param string $attributeCode
     * @param int $storeId
     * @return array<int, string> Product id to brand
     */
    private function brands(array $products, string $attributeCode, int $storeId): array
    {
        return $this->attributeTexts->texts(
            $attributeCode,
            $this->attributeValues->values($products, $attributeCode, $storeId),
            $storeId
        );
    }
}
