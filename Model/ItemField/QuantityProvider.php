<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\ItemField;

use Hryvinskyi\DataLayerApi\Api\ItemFieldProviderInterface;

/**
 * Fills `quantity` from the item subject, as a whole number when it is one
 */
class QuantityProvider implements ItemFieldProviderInterface
{
    /**
     * @inheritDoc
     */
    public function provide(array $subjects, string $eventName): array
    {
        $fields = [];
        foreach ($subjects as $key => $subject) {
            $quantity = $subject->getQuantity();
            $fields[$key] = ['quantity' => floor($quantity) === $quantity ? (int) $quantity : $quantity];
        }

        return $fields;
    }
}
