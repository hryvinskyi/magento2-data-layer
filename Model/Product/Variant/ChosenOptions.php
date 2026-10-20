<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Product\Variant;

use Hryvinskyi\DataLayer\Api\Product\ChosenOptionsInterface;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Catalog\Model\Product\Configuration\Item\ItemInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Item as OrderItem;

/**
 * Reads chosen options from Magento's product configuration of a cart line and from an order line's product options
 *
 * A cart line's options come from the product configuration registered for its product type (which, for a
 * configurable line, lists the chosen attributes before the custom options); an order line's come from the product
 * options stored with it when the order was placed.
 */
class ChosenOptions implements ChosenOptionsInterface
{
    private const DEFAULT_CONFIGURATION = 'default';
    private const SEPARATOR = ', ';

    /**
     * @param ConfigurationPool $configurations Magento's product configuration per product type
     */
    public function __construct(
        private readonly ConfigurationPool $configurations
    ) {
    }

    /**
     * @inheritDoc
     */
    public function ofCartLine(CartItemInterface $line): array
    {
        if (!$line instanceof ItemInterface) {
            return [];
        }

        return $this->rows(
            $this->configurations->getByProductType((string) $line->getProductType())->getOptions($line)
        );
    }

    /**
     * @inheritDoc
     */
    public function customOfCartLine(CartItemInterface $line): array
    {
        if (!$line instanceof ItemInterface) {
            return [];
        }

        return $this->rows($this->configurations->getByProductType(self::DEFAULT_CONFIGURATION)->getOptions($line));
    }

    /**
     * @inheritDoc
     */
    public function ofOrderLine(OrderItemInterface $line, array $groups): array
    {
        if (!$line instanceof OrderItem) {
            return [];
        }
        $productOptions = $line->getProductOptions();
        $options = [];
        foreach ($groups as $group) {
            $rows = $productOptions[$group] ?? [];
            if (is_array($rows)) {
                array_push($options, ...$this->rows($rows));
            }
        }

        return $options;
    }

    /**
     * @inheritDoc
     */
    public function toText(array $options): ?string
    {
        $pairs = [];
        foreach ($options as $option) {
            $pairs[] = $option['label'] . ': ' . $option['value'];
        }

        return $pairs === [] ? null : implode(self::SEPARATOR, $pairs);
    }

    /**
     * Keep the rows that carry a label and a value, as plain text
     *
     * A row's printable value is preferred over its display value; a list of values is joined.
     *
     * @param array<mixed> $rows
     * @return list<array{label: string, value: string}>
     */
    private function rows(array $rows): array
    {
        $options = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = $this->plain($row['label'] ?? null);
            $value = $this->plain($row['print_value'] ?? null);
            if ($value === '') {
                $value = $this->plain($row['value'] ?? null);
            }
            if ($label !== '' && $value !== '') {
                $options[] = ['label' => $label, 'value' => $value];
            }
        }

        return $options;
    }

    /**
     * Turn a stored option value into plain text
     *
     * @param mixed $value
     * @return string Empty when the value is not text, or a list without text
     */
    private function plain(mixed $value): string
    {
        if (is_array($value)) {
            $parts = array_filter(array_map(fn (mixed $part): string => $this->plain($part), $value));

            return implode(self::SEPARATOR, $parts);
        }
        if (!is_scalar($value) || is_bool($value)) {
            return '';
        }

        return trim(htmlspecialchars_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5));
    }
}
