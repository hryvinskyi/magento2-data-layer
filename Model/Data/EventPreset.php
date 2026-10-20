<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Data;

use Hryvinskyi\DataLayer\Api\Data\EventPresetInterface;

/**
 * An event preset, valid by construction
 *
 * A key that is not lower-case letters, digits and underscores, an empty label, an icon that is not a glyph name, an
 * empty event list and an event name that is not a GA4 event name are refused when the preset is created. The label
 * and the description are kept as registered, in the source language, and translated each time they are read, into
 * the locale of the request reading them.
 */
class EventPreset implements EventPresetInterface
{
    /**
     * A preset key, and a glyph name: lower-case letters, digits and underscores
     */
    public const KEY_PATTERN = '/^[a-z0-9_]+$/D';

    /**
     * @var list<string>
     */
    private readonly array $events;

    /**
     * @param string $key
     * @param string $label
     * @param list<string> $events The names of the events the preset switches on
     * @param string $description
     * @param int $sortOrder
     * @param string|null $icon A glyph name, or null for none
     * @throws \InvalidArgumentException When the key, the label, the icon or the events are unusable
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        array $events,
        private readonly string $description = '',
        private readonly int $sortOrder = 0,
        private readonly ?string $icon = null
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new \InvalidArgumentException(
                sprintf('"%s" is not an event preset key: use lower-case letters, digits and underscores.', $key)
            );
        }
        if (trim($label) === '') {
            throw new \InvalidArgumentException(sprintf('The event preset "%s" needs a label.', $key));
        }
        if ($icon !== null && preg_match(self::KEY_PATTERN, $icon) !== 1) {
            throw new \InvalidArgumentException(sprintf('The event preset "%s" names an unusable icon.', $key));
        }
        if ($events === []) {
            throw new \InvalidArgumentException(sprintf('The event preset "%s" selects no event.', $key));
        }
        foreach ($events as $eventName) {
            if (preg_match(Ga4Name::PATTERN, $eventName) !== 1) {
                throw new \InvalidArgumentException(
                    sprintf('The event preset "%s" selects "%s", which is not a GA4 event name.', $key, $eventName)
                );
            }
        }
        $this->events = array_values(array_unique($events));
    }

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return (string) __($this->label);
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return $this->description === '' ? '' : (string) __($this->description);
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @inheritDoc
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @inheritDoc
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
