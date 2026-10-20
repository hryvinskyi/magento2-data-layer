<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\Data\KnownEventInterfaceFactory;
use Hryvinskyi\DataLayer\Api\KnownEventsInterface;
use Hryvinskyi\DataLayer\Model\Data\Ga4Name;
use Hryvinskyi\DataLayer\Model\Data\KnownEvent;

/**
 * The known events, registered through the `events` argument, keyed by GA4 event name
 *
 * Each registration is an array with a `label` and, for the admin's event list, a `group`, a `trigger`, a `source`
 * and an optional `hint`; a plain text registration is a label alone. `groupCode` and `sourceCode` (lower-case letters
 * and underscores, empty when left out) name the group and the source in a way that does not change with the admin's
 * language. `browserOriginated` (a boolean, false when left out) marks an event the shopper's browser detects, the
 * only kind a browser may post to the server. A name that is not a GA4 event name, a missing label, a description
 * that is not text, a code that is not a code or a flag that is not a boolean fails when the object is built, so a
 * typo in the wiring cannot hide an event or open the server to one. Descriptions are built only when asked for:
 * reporting an event only needs to know its name. Texts are kept in the source language they are registered in and
 * translated when read, so a shared list answers each request in its own locale.
 *
 * @phpstan-type Registration array{
 *     label: string,
 *     group: string,
 *     trigger: string,
 *     source: string,
 *     hint: string,
 *     browserOriginated: bool,
 *     groupCode: string,
 *     sourceCode: string
 * }
 */
class KnownEvents implements KnownEventsInterface
{
    private const DESCRIPTION_KEYS = ['group', 'trigger', 'source', 'hint'];

    private const CODE_KEYS = ['groupCode', 'sourceCode'];

    /**
     * @var array<string, Registration>
     */
    private readonly array $events;

    /**
     * @param KnownEventInterfaceFactory $eventFactory
     * @param array<mixed> $events Each event's label, or its label and description, keyed by GA4 event name
     * @throws \InvalidArgumentException When a name is not a GA4 event name, a label is missing, a text is not text, a
     *     code is not a code or the browser flag is not a boolean
     */
    public function __construct(
        private readonly KnownEventInterfaceFactory $eventFactory,
        array $events = []
    ) {
        $known = [];
        foreach ($events as $name => $registration) {
            if (!is_string($name) || preg_match(Ga4Name::PATTERN, $name) !== 1) {
                throw new \InvalidArgumentException(
                    sprintf('"%s" is registered as a known event but is not a GA4 event name.', (string) $name)
                );
            }
            $known[$name] = $this->description(
                $name,
                is_array($registration) ? $registration : ['label' => $registration]
            );
        }
        $this->events = $known;
    }

    /**
     * @inheritDoc
     */
    public function getLabels(): array
    {
        return array_map(static fn (array $event): string => (string) __($event['label']), $this->events);
    }

    /**
     * @inheritDoc
     */
    public function getEvents(): array
    {
        $descriptions = [];
        foreach ($this->events as $name => $event) {
            $descriptions[] = $this->eventFactory->create(['name' => $name] + $event);
        }

        return $descriptions;
    }

    /**
     * @inheritDoc
     */
    public function isKnown(string $eventName): bool
    {
        return isset($this->events[$eventName]);
    }

    /**
     * @inheritDoc
     */
    public function isBrowserOriginated(string $eventName): bool
    {
        return isset($this->events[$eventName]) && $this->events[$eventName]['browserOriginated'];
    }

    /**
     * Read one registration's texts
     *
     * @param string $name
     * @param array<mixed> $registration
     * @return Registration
     * @throws \InvalidArgumentException When the label is missing, a text is not text, a code is not a code or the
     *     flag is not a boolean
     */
    private function description(string $name, array $registration): array
    {
        $label = $this->text($name, $registration['label'] ?? '');
        if ($label === '') {
            throw new \InvalidArgumentException(sprintf('The known event "%s" needs a text label.', $name));
        }
        $browserOriginated = $registration['browserOriginated'] ?? false;
        if (!is_bool($browserOriginated)) {
            throw new \InvalidArgumentException(
                sprintf('The known event "%s" must say whether the browser detects it with a boolean.', $name)
            );
        }
        $description = [
            'label' => $label,
            'group' => '',
            'trigger' => '',
            'source' => '',
            'hint' => '',
            'browserOriginated' => $browserOriginated,
            'groupCode' => '',
            'sourceCode' => '',
        ];
        foreach (self::DESCRIPTION_KEYS as $key) {
            $description[$key] = $this->text($name, $registration[$key] ?? '');
        }
        foreach (self::CODE_KEYS as $key) {
            if (array_key_exists($key, $registration)) {
                $description[$key] = $this->code($name, $registration[$key]);
            }
        }

        return $description;
    }

    /**
     * Read one code of a registration
     *
     * @param string $name
     * @param mixed $value
     * @return string
     * @throws \InvalidArgumentException When the value is not lower-case letters and underscores
     */
    private function code(string $name, mixed $value): string
    {
        if (!is_string($value) || preg_match(KnownEvent::CODE_PATTERN, $value) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'The known event "%s" is given a group or source code that is not lower-case letters and underscores.',
                $name
            ));
        }

        return $value;
    }

    /**
     * Read one text of a registration
     *
     * @param string $name
     * @param mixed $value
     * @return string Trimmed
     * @throws \InvalidArgumentException When the value is not text
     */
    private function text(string $name, mixed $value): string
    {
        if (!is_string($value) && !$value instanceof \Stringable) {
            throw new \InvalidArgumentException(
                sprintf('The known event "%s" is described with a value that is not text.', $name)
            );
        }

        return trim((string) $value);
    }
}
