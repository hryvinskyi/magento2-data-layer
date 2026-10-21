<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\AttributeTextReaderInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\LocalizedException;

/**
 * Reads attribute text through the reader registered for the attribute's input type
 *
 * The readers are keyed by the attribute's frontend input (`select`, `multiselect`, ...); an input type without a
 * reader of its own is read by the default reader. An attribute that does not exist yields no text.
 */
class AttributeTextReader implements AttributeTextReaderInterface
{
    /**
     * @param EavConfig $eavConfig
     * @param AttributeTextReaderInterface $defaultReader Reads an attribute whose input type has no reader of its own
     * @param array<string, AttributeTextReaderInterface> $readers Frontend input to the reader of that input type
     */
    public function __construct(
        private readonly EavConfig $eavConfig,
        private readonly AttributeTextReaderInterface $defaultReader,
        private readonly array $readers = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function texts(string $attributeCode, array $values, int $storeId): array
    {
        if ($values === []) {
            return [];
        }

        try {
            $attribute = $this->eavConfig->getAttribute(ProductAttributeInterface::ENTITY_TYPE_CODE, $attributeCode);
        } catch (LocalizedException) {
            return [];
        }
        if ((int) $attribute->getAttributeId() <= 0) {
            return [];
        }

        $reader = $this->readers[(string) $attribute->getFrontendInput()] ?? $this->defaultReader;

        return $reader->texts($attributeCode, $values, $storeId);
    }
}
