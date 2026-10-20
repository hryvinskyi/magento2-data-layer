<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

use Hryvinskyi\DataLayer\Api\Data\EventPresetInterface;

/**
 * The event presets modules register: named event selections a store can switch on in one step
 *
 * The list is open: a module registers a preset of its own, or adds its events to a preset another module registers.
 *
 * @api
 */
interface EventPresetsInterface
{
    /**
     * Get every preset that selects at least one known event
     *
     * @return list<EventPresetInterface> Sorted by sort order, then by key
     */
    public function getPresets(): array;
}
