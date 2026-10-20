<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\Data\EventPresetInterface;
use Hryvinskyi\DataLayer\Api\Data\EventPresetInterfaceFactory;
use Hryvinskyi\DataLayer\Api\Data\KnownEventInterface;
use Hryvinskyi\DataLayer\Api\EventPresetsInterface;
use Hryvinskyi\DataLayer\Api\KnownEventsInterface;
use Hryvinskyi\DataLayer\Model\Data\EventPreset;

/**
 * The event presets, registered through the `presets` argument, keyed by preset key
 *
 * Each registration is an array with a `label`, an optional `description`, a `sortOrder` (a whole number, 0 when left
 * out), an optional `icon` (a glyph name), an optional `extends` (another preset's key) and `events`: event names
 * keyed by the same name, so a module adds an event to a preset another module registers by merging one item into
 * that preset's `events`. A preset selects its own events and every event of the preset it extends.
 *
 * Only known events are selected, in the order the known events list them: an event whose module is absent is left
 * out, and a preset left with no event at all (its modules are absent) is dropped from the list without a word.
 *
 * A key or an icon that is not lower-case letters, digits and underscores, a missing label, a value that is not
 * text, a sort order that is not a whole number, an `extends` naming a preset that is not registered and a chain of
 * `extends` that comes back to where it started all fail when the object is built, naming the preset, so a typo in
 * the wiring cannot silently change what a preset switches on.
 *
 * @phpstan-type Registration array{
 *     label: string,
 *     description: string,
 *     sortOrder: int,
 *     icon: string|null,
 *     extends: string|null,
 *     events: list<string>
 * }
 */
class EventPresets implements EventPresetsInterface
{
    /**
     * @var array<string, Registration>
     */
    private readonly array $presets;

    /**
     * @var array<string, list<string>> Every event name each preset selects, its own and those it extends
     */
    private readonly array $selections;

    /**
     * @param KnownEventsInterface $knownEvents
     * @param EventPresetInterfaceFactory $presetFactory
     * @param array<mixed> $presets Each preset's registration, keyed by preset key
     * @throws \InvalidArgumentException When a registration is unusable, extends an unregistered preset or extends
     *     itself through a chain
     */
    public function __construct(
        private readonly KnownEventsInterface $knownEvents,
        private readonly EventPresetInterfaceFactory $presetFactory,
        array $presets = []
    ) {
        $registered = [];
        foreach ($presets as $key => $registration) {
            if (!is_string($key) || preg_match(EventPreset::KEY_PATTERN, $key) !== 1) {
                throw new \InvalidArgumentException(sprintf(
                    'The event preset "%s" needs a key of lower-case letters, digits and underscores.',
                    (string) $key
                ));
            }
            if (!is_array($registration)) {
                throw new \InvalidArgumentException(
                    sprintf('The event preset "%s" must be registered as an array.', $key)
                );
            }
            $registered[$key] = $this->registration($key, $registration);
        }
        $this->presets = $registered;

        $selections = [];
        foreach (array_keys($registered) as $key) {
            $selections[$key] = $this->selection($key, []);
        }
        $this->selections = $selections;
    }

    /**
     * @inheritDoc
     */
    public function getPresets(): array
    {
        $catalogue = array_map(
            static fn (KnownEventInterface $event): string => $event->getName(),
            $this->knownEvents->getEvents()
        );

        $presets = [];
        foreach ($this->presets as $key => $preset) {
            $selected = array_flip($this->selections[$key]);
            $events = array_values(array_filter(
                $catalogue,
                static fn (string $eventName): bool => isset($selected[$eventName])
            ));
            if ($events === []) {
                continue;
            }
            $presets[] = $this->presetFactory->create([
                'key' => $key,
                'label' => $preset['label'],
                'events' => $events,
                'description' => $preset['description'],
                'sortOrder' => $preset['sortOrder'],
                'icon' => $preset['icon'],
            ]);
        }

        usort(
            $presets,
            static fn (EventPresetInterface $a, EventPresetInterface $b): int =>
                [$a->getSortOrder(), $a->getKey()] <=> [$b->getSortOrder(), $b->getKey()]
        );

        return $presets;
    }

    /**
     * Collect every event name a preset selects, following its `extends` chain
     *
     * @param string $key
     * @param list<string> $chain The presets already followed to reach this one
     * @return list<string>
     * @throws \InvalidArgumentException When the preset extends an unregistered preset or the chain comes back
     */
    private function selection(string $key, array $chain): array
    {
        $repeatedAt = array_search($key, $chain, true);
        if ($repeatedAt !== false) {
            throw new \InvalidArgumentException(sprintf(
                'The event preset "%s" extends itself through %s.',
                $key,
                implode(' > ', [...array_slice($chain, $repeatedAt), $key])
            ));
        }
        $preset = $this->presets[$key];
        $extends = $preset['extends'];
        if ($extends === null) {
            return $preset['events'];
        }
        if (!isset($this->presets[$extends])) {
            throw new \InvalidArgumentException(
                sprintf('The event preset "%s" extends "%s", which is not registered.', $key, $extends)
            );
        }

        return array_values(array_unique([...$preset['events'], ...$this->selection($extends, [...$chain, $key])]));
    }

    /**
     * Read one registration
     *
     * @param string $key
     * @param array<mixed> $registration
     * @return Registration
     * @throws \InvalidArgumentException When the label is missing or a value has the wrong type
     */
    private function registration(string $key, array $registration): array
    {
        $label = $this->text($key, 'label', $registration['label'] ?? '');
        if ($label === '') {
            throw new \InvalidArgumentException(sprintf('The event preset "%s" needs a text label.', $key));
        }
        $sortOrder = filter_var($registration['sortOrder'] ?? 0, FILTER_VALIDATE_INT);
        if (!is_int($sortOrder)) {
            throw new \InvalidArgumentException(
                sprintf('The event preset "%s" needs a whole number as its sort order.', $key)
            );
        }
        $icon = $this->text($key, 'icon', $registration['icon'] ?? '');
        if ($icon !== '' && preg_match(EventPreset::KEY_PATTERN, $icon) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'The event preset "%s" needs a glyph name of lower-case letters, digits and underscores as its icon.',
                $key
            ));
        }
        $extends = $this->text($key, 'extends', $registration['extends'] ?? '');

        return [
            'label' => $label,
            'description' => $this->text($key, 'description', $registration['description'] ?? ''),
            'sortOrder' => $sortOrder,
            'icon' => $icon !== '' ? $icon : null,
            'extends' => $extends !== '' ? $extends : null,
            'events' => $this->eventNames($key, $registration['events'] ?? []),
        ];
    }

    /**
     * Read a registration's event names
     *
     * @param string $key
     * @param mixed $events
     * @return list<string>
     * @throws \InvalidArgumentException When the events are not a list of names
     */
    private function eventNames(string $key, mixed $events): array
    {
        if (!is_array($events)) {
            throw new \InvalidArgumentException(
                sprintf('The event preset "%s" must list its events as an array of event names.', $key)
            );
        }
        $names = [];
        foreach ($events as $eventName) {
            if (!is_string($eventName) || trim($eventName) === '') {
                throw new \InvalidArgumentException(
                    sprintf('The event preset "%s" lists an event that is not an event name.', $key)
                );
            }
            $names[] = trim($eventName);
        }

        return array_values(array_unique($names));
    }

    /**
     * Read one text of a registration
     *
     * @param string $key
     * @param string $field
     * @param mixed $value
     * @return string Trimmed
     * @throws \InvalidArgumentException When the value is not text
     */
    private function text(string $key, string $field, mixed $value): string
    {
        if (!is_string($value) && !$value instanceof \Stringable) {
            throw new \InvalidArgumentException(
                sprintf('The event preset "%s" gives a %s that is not text.', $key, $field)
            );
        }

        return trim((string) $value);
    }
}
