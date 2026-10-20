<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event;

use Hryvinskyi\DataLayer\Api\Catalog\LineProductLoaderInterface;
use Hryvinskyi\DataLayer\Api\Event\OrderItemSubjectsInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterfaceFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * Builds item subjects from order lines, with every line's product loaded in one product collection
 *
 * A product that is gone from the catalogue is stood in for by a product made from the line's own record (id, SKU,
 * name, type), so a sold line is still reported with what the order knows about it.
 */
class OrderItemSubjects implements OrderItemSubjectsInterface
{
    /**
     * @param LineProductLoaderInterface $productLoader
     * @param ItemSubjectInterfaceFactory $subjectFactory
     * @param ProductInterfaceFactory $productFactory
     */
    public function __construct(
        private readonly LineProductLoaderInterface $productLoader,
        private readonly ItemSubjectInterfaceFactory $subjectFactory,
        private readonly ProductInterfaceFactory $productFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function subjects(array $orderItems, int $storeId): array
    {
        $visible = [];
        $children = [];
        $productIds = [];
        foreach ($orderItems as $line) {
            $productIds[] = (int) $line->getProductId();
            $parentKey = $this->parentKey($line);
            if ($parentKey === null) {
                $visible[] = $line;
                continue;
            }
            $children[$parentKey][] = $line;
        }
        if ($visible === []) {
            return [];
        }

        $products = $this->productLoader->byIds($productIds, $storeId);

        $subjects = [];
        foreach ($visible as $line) {
            $lineChildren = $children['object:' . spl_object_id($line)]
                ?? $children['id:' . (int) $line->getItemId()]
                ?? [];
            $subjects[] = $this->subjectFactory->create([
                'product' => $this->product($line, $products, $storeId),
                'storeId' => $storeId,
                'quantity' => (float) $line->getQtyOrdered(),
                'childProduct' => count($lineChildren) === 1
                    ? $this->product($lineChildren[0], $products, $storeId)
                    : null,
                'orderItem' => $line,
            ]);
        }

        return $subjects;
    }

    /**
     * Get the key that links a child line to its parent line
     *
     * The key names the parent object when the line holds it (an order whose lines were just built), the parent's id
     * otherwise (an order read back).
     *
     * @param OrderItemInterface $line
     * @return string|null Null for a line without a parent, which is a visible line
     */
    private function parentKey(OrderItemInterface $line): ?string
    {
        $parent = $line->getParentItem();
        if ($parent !== null) {
            return 'object:' . spl_object_id($parent);
        }
        $parentId = (int) $line->getParentItemId();

        return $parentId > 0 ? 'id:' . $parentId : null;
    }

    /**
     * Get a line's catalogue product, or a stand-in made from the line when the product no longer exists
     *
     * @param OrderItemInterface $line
     * @param array<int,ProductInterface> $products The loaded products by id
     * @param int $storeId
     * @return ProductInterface
     */
    private function product(OrderItemInterface $line, array $products, int $storeId): ProductInterface
    {
        $productId = (int) $line->getProductId();
        if (isset($products[$productId])) {
            return $products[$productId];
        }

        $standIn = $this->productFactory->create(['data' => ['store_id' => $storeId]]);
        if ($productId > 0) {
            $standIn->setId($productId);
        }
        $standIn->setSku((string) $line->getSku());
        $standIn->setName((string) $line->getName());
        $standIn->setTypeId((string) $line->getProductType());

        return $standIn;
    }
}
