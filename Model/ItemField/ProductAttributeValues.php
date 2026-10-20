<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\ItemField;

use Hryvinskyi\DataLayer\Api\Catalog\AttributeValueReaderInterface;
use Hryvinskyi\DataLayer\Api\ItemField\ProductAttributeValuesInterface;
use Magento\Framework\DataObject;

/**
 * Takes an attribute's value from the loaded product when it was loaded with it, and reads the rest in one go
 */
class ProductAttributeValues implements ProductAttributeValuesInterface
{
    private const PRODUCT_ID = 'entity_id';

    /**
     * @param AttributeValueReaderInterface $reader
     */
    public function __construct(
        private readonly AttributeValueReaderInterface $reader
    ) {
    }

    /**
     * @inheritDoc
     */
    public function values(array $products, string $attributeCode, int $storeId): array
    {
        $values = [];
        $missing = [];
        foreach ($products as $productId => $product) {
            if ($attributeCode === self::PRODUCT_ID) {
                $values[$productId] = (string) $productId;
                continue;
            }
            if (!$product instanceof DataObject || !$product->hasData($attributeCode)) {
                $missing[] = $productId;
                continue;
            }
            $value = $this->text($product->getData($attributeCode));
            if ($value !== '') {
                $values[$productId] = $value;
            }
        }

        if ($missing !== []) {
            foreach ($this->reader->read($missing, $attributeCode, $storeId) as $productId => $value) {
                if (trim($value) !== '') {
                    $values[$productId] = trim($value);
                }
            }
        }

        return $values;
    }

    /**
     * Read a loaded value as text; a list of values (several options) is joined with commas, as it is stored
     *
     * @param mixed $value
     * @return string Empty when there is no text value
     */
    private function text(mixed $value): string
    {
        if (is_array($value)) {
            return implode(',', array_filter(array_map(fn (mixed $part): string => $this->text($part), $value)));
        }

        return is_scalar($value) && !is_bool($value) ? trim((string) $value) : '';
    }
}
