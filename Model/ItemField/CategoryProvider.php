<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\ItemField;

use Hryvinskyi\DataLayer\Api\Catalog\CategoryPathReaderInterface;
use Hryvinskyi\DataLayerApi\Api\ItemFieldProviderInterface;

/**
 * Fills `item_category` … `item_category5` with the names along the product's category path, top level first
 *
 * The paths of all products are read at once. An item shown in a category's product list (list id
 * `category_<category id>`) prefers that category's path for its product.
 */
class CategoryProvider implements ItemFieldProviderInterface
{
    private const CATEGORY_LIST = '/^category_(\d+)$/D';
    private const MAX_LEVELS = 5;

    /**
     * @param CategoryPathReaderInterface $categoryPaths
     */
    public function __construct(
        private readonly CategoryPathReaderInterface $categoryPaths
    ) {
    }

    /**
     * @inheritDoc
     */
    public function provide(array $subjects, string $eventName): array
    {
        $productIds = [];
        $preferred = [];
        foreach ($subjects as $subject) {
            $productId = (int) $subject->getProduct()->getId();
            if ($productId <= 0) {
                continue;
            }
            $storeId = $subject->getStoreId();
            $productIds[$storeId][$productId] = $productId;
            if (preg_match(self::CATEGORY_LIST, (string) $subject->getListId(), $match) === 1) {
                $preferred[$storeId][$productId] = (int) $match[1];
            }
        }

        $paths = [];
        foreach ($productIds as $storeId => $ids) {
            $paths[$storeId] = $this->categoryPaths->read(array_values($ids), $storeId, $preferred[$storeId] ?? []);
        }

        $fields = [];
        foreach ($subjects as $key => $subject) {
            $names = $paths[$subject->getStoreId()][(int) $subject->getProduct()->getId()] ?? [];
            $itemFields = [];
            foreach (array_slice($names, 0, self::MAX_LEVELS) as $level => $name) {
                $itemFields[$level === 0 ? 'item_category' : 'item_category' . ($level + 1)] = $name;
            }
            if ($itemFields !== []) {
                $fields[$key] = $itemFields;
            }
        }

        return $fields;
    }
}
