<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\ItemField;

use Hryvinskyi\DataLayer\Api\Catalog\OptionLabelReaderInterface;
use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\ItemField\ProductAttributeValuesInterface;
use Hryvinskyi\DataLayerApi\Api\ItemFieldProviderInterface;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * Fills `item_brand` with the store labels of the product's options of the store's brand attribute
 *
 * Nothing is done for a store view without a brand attribute. The option ids of all products are read at once (or
 * taken from the loaded products) and their labels in one go; several options are joined with a comma.
 */
class BrandProvider implements ItemFieldProviderInterface
{
    private const SEPARATOR = ', ';

    /**
     * @param ConfigInterface $config
     * @param ProductAttributeValuesInterface $attributeValues
     * @param OptionLabelReaderInterface $optionLabels
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ProductAttributeValuesInterface $attributeValues,
        private readonly OptionLabelReaderInterface $optionLabels
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
        $optionIds = [];
        foreach ($this->attributeValues->values($products, $attributeCode, $storeId) as $productId => $value) {
            $optionIds[$productId] = array_values(array_filter(
                array_map('trim', explode(',', $value)),
                static fn (string $optionId): bool => $optionId !== ''
            ));
        }
        if ($optionIds === []) {
            return [];
        }

        $labels = $this->optionLabels->labels(
            $attributeCode,
            array_values(array_unique(array_merge(...array_values($optionIds)))),
            $storeId
        );

        $brands = [];
        foreach ($optionIds as $productId => $ids) {
            $names = [];
            foreach ($ids as $optionId) {
                $label = trim($labels[$optionId] ?? '');
                if ($label !== '') {
                    $names[] = $label;
                }
            }
            if ($names !== []) {
                $brands[$productId] = implode(self::SEPARATOR, $names);
            }
        }

        return $brands;
    }
}
