<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Event;

use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * Turns an order's lines into item subjects, loading their catalogue products in one go
 */
interface OrderItemSubjectsInterface
{
    /**
     * Get one subject per visible order line, in line order
     *
     * A line's product is the product it was ordered as (a configurable line's parent); a line with exactly one child
     * line (a configurable's chosen variant) carries that child's product too. A line whose product no longer exists
     * in the catalogue still makes a subject, from what the line itself recorded (product id, SKU, name, type).
     *
     * @param array<OrderItemInterface> $orderItems Every line of the order, child lines included
     * @param int $storeId The order's store view
     * @return list<ItemSubjectInterface>
     */
    public function subjects(array $orderItems, int $storeId): array;
}
