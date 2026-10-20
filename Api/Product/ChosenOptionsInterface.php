<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Product;

use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

/**
 * Reads the options a shopper chose on a cart or order line, and spells them as variant text
 *
 * Every option is a label and a plain-text value; markup in a stored value is removed.
 */
interface ChosenOptionsInterface
{
    /**
     * Get the options of a cart line as the store's product configuration lists them for its product type
     *
     * @param CartItemInterface $line
     * @return list<array{label: string, value: string}>
     */
    public function ofCartLine(CartItemInterface $line): array;

    /**
     * Get only the custom options of a cart line
     *
     * @param CartItemInterface $line
     * @return list<array{label: string, value: string}>
     */
    public function customOfCartLine(CartItemInterface $line): array;

    /**
     * Get the options an order line stored under the given groups of its product options, group by group
     *
     * @param OrderItemInterface $line
     * @param list<string> $groups Product option groups such as `attributes_info`, `options`, `additional_options`
     * @return list<array{label: string, value: string}>
     */
    public function ofOrderLine(OrderItemInterface $line, array $groups): array;

    /**
     * Spell options as variant text
     *
     * @param list<array{label:string,value:string}> $options
     * @return string|null `Label: Value, Label: Value`; null when there is no option
     */
    public function toText(array $options): ?string;
}
