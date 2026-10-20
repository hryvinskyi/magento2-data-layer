<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Event;

use Hryvinskyi\DataLayer\Api\Data\ConversionValueInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Tells what an order's conversion is worth, one way of valuing it per implementation
 */
interface ConversionValueSourceInterface
{
    /**
     * Get the order's conversion value
     *
     * @param OrderInterface $order
     * @return ConversionValueInterface|null Null when this source has no value for the order (the conversion is then
     *      reported without one)
     */
    public function valueOf(OrderInterface $order): ?ConversionValueInterface;
}
