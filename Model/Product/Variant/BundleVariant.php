<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Product\Variant;

use Hryvinskyi\DataLayer\Api\Product\ChosenOptionsInterface;
use Hryvinskyi\DataLayer\Api\Product\LineVariantStrategyInterface;
use Magento\Catalog\Model\Product\Configuration\Item\Option\OptionInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Item as OrderItem;

/**
 * The variant of a bundle line: each chosen selection under its option's title, then the custom options
 *
 * Selections are named without their quantity or price. A cart line reads them from its child lines (each child keeps
 * the title of the option it was chosen for); an order line from the bundle options stored with it.
 */
class BundleVariant implements LineVariantStrategyInterface
{
    private const SELECTION_ATTRIBUTES = 'bundle_selection_attributes';
    private const ORDER_BUNDLE_OPTIONS = 'bundle_options';
    private const ORDER_GROUPS = ['options', 'additional_options'];

    /**
     * @param ChosenOptionsInterface $chosenOptions
     * @param SerializerInterface $serializer Reads the selection attributes the way the cart stored them
     */
    public function __construct(
        private readonly ChosenOptionsInterface $chosenOptions,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function variant(CartItemInterface|OrderItemInterface $line): ?string
    {
        if ($line instanceof OrderItemInterface) {
            return $this->chosenOptions->toText(array_merge(
                $this->orderSelections($line),
                $this->chosenOptions->ofOrderLine($line, self::ORDER_GROUPS)
            ));
        }

        return $this->chosenOptions->toText(array_merge(
            $this->cartSelections($line),
            $this->chosenOptions->customOfCartLine($line)
        ));
    }

    /**
     * Get the selections of a bundle order line
     *
     * @param OrderItemInterface $line
     * @return list<array{label: string, value: string}>
     */
    private function orderSelections(OrderItemInterface $line): array
    {
        if (!$line instanceof OrderItem) {
            return [];
        }
        $bundleOptions = $line->getProductOptions()[self::ORDER_BUNDLE_OPTIONS] ?? [];
        if (!is_array($bundleOptions)) {
            return [];
        }

        $selections = [];
        foreach ($bundleOptions as $option) {
            $label = is_array($option) ? $this->text($option['label'] ?? null) : '';
            $values = is_array($option) ? ($option['value'] ?? []) : [];
            if ($label === '' || !is_array($values)) {
                continue;
            }
            foreach ($values as $value) {
                $title = is_array($value) ? $this->text($value['title'] ?? null) : '';
                if ($title !== '') {
                    $selections[] = ['label' => $label, 'value' => $title];
                }
            }
        }

        return $selections;
    }

    /**
     * Get the selections of a bundle cart line from its child lines
     *
     * @param CartItemInterface $line
     * @return list<array{label: string, value: string}>
     */
    private function cartSelections(CartItemInterface $line): array
    {
        if (!$line instanceof Item) {
            return [];
        }

        $selections = [];
        foreach ($line->getChildren() as $child) {
            if (!$child instanceof Item) {
                continue;
            }
            $label = $this->optionTitle($child);
            $name = $this->text($child->getName());
            if ($label !== '' && $name !== '') {
                $selections[] = ['label' => $label, 'value' => $name];
            }
        }

        return $selections;
    }

    /**
     * Get the title of the bundle option a child line was chosen for
     *
     * @param Item $child
     * @return string Empty when the child carries no selection attributes
     */
    private function optionTitle(Item $child): string
    {
        $option = $child->getOptionsByCode()[self::SELECTION_ATTRIBUTES] ?? null;
        $stored = $option instanceof OptionInterface ? $option->getValue() : null;
        if (!is_string($stored) || $stored === '') {
            return '';
        }
        try {
            $attributes = $this->serializer->unserialize($stored);
        } catch (\InvalidArgumentException) {
            return '';
        }

        return is_array($attributes) ? $this->text($attributes['option_label'] ?? null) : '';
    }

    /**
     * Read a stored title as trimmed text
     *
     * @param mixed $value
     * @return string Empty when the value is not text
     */
    private function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
