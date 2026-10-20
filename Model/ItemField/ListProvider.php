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
 * Fills `item_list_id`, `item_list_name` and `index` for an item shown in a list
 */
class ListProvider implements ItemFieldProviderInterface
{
    /**
     * @inheritDoc
     */
    public function provide(array $subjects, string $eventName): array
    {
        $fields = [];
        foreach ($subjects as $key => $subject) {
            $itemFields = [];
            $listId = (string) $subject->getListId();
            if ($listId !== '') {
                $itemFields['item_list_id'] = $listId;
            }
            $listName = (string) $subject->getListName();
            if ($listName !== '') {
                $itemFields['item_list_name'] = $listName;
            }
            $index = $subject->getIndex();
            if ($index !== null) {
                $itemFields['index'] = $index;
            }
            if ($itemFields !== []) {
                $fields[$key] = $itemFields;
            }
        }

        return $fields;
    }
}
