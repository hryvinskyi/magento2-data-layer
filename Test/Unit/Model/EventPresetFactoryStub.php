<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Api\Data\EventPresetInterface;
use Hryvinskyi\DataLayer\Api\Data\EventPresetInterfaceFactory;
use Hryvinskyi\DataLayer\Model\Data\EventPreset;

/**
 * Builds event presets the way the generated factory does, from named arguments, without a container
 */
class EventPresetFactoryStub extends EventPresetInterfaceFactory
{
    /**
     * Build without an object manager
     */
    public function __construct()
    {
    }

    /**
     * Build a preset from named arguments
     *
     * @param array<string, mixed> $data
     * @return EventPresetInterface
     */
    public function create(array $data = [])
    {
        $events = [];
        foreach (is_array($data['events'] ?? null) ? $data['events'] : [] as $eventName) {
            $events[] = is_string($eventName) ? $eventName : '';
        }
        $icon = $data['icon'] ?? null;

        return new EventPreset(
            is_string($data['key'] ?? null) ? $data['key'] : '',
            is_string($data['label'] ?? null) ? $data['label'] : '',
            $events,
            is_string($data['description'] ?? null) ? $data['description'] : '',
            is_int($data['sortOrder'] ?? null) ? $data['sortOrder'] : 0,
            is_string($icon) ? $icon : null
        );
    }
}
