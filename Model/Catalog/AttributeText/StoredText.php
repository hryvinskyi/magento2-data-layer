<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Catalog\AttributeText;

use Hryvinskyi\DataLayer\Api\Catalog\AttributeTextReaderInterface;

/**
 * Text of an attribute whose stored value is the text itself, such as a text field: the value, trimmed
 */
class StoredText implements AttributeTextReaderInterface
{
    /**
     * @inheritDoc
     */
    public function texts(string $attributeCode, array $values, int $storeId): array
    {
        $texts = [];
        foreach ($values as $productId => $value) {
            $text = trim($value);
            if ($text !== '') {
                $texts[$productId] = $text;
            }
        }

        return $texts;
    }
}
