<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

use Hryvinskyi\DataLayer\Api\Data\KnownEventInterface;

/**
 * The events the data layer knows how to report, with their admin labels and descriptions
 *
 * The list is open: a module that adds an event registers it here, and it is reported unless a store view disables
 * it, with no configuration change.
 */
interface KnownEventsInterface
{
    /**
     * Get every known event's label, keyed by event name
     *
     * @return array<string, string> Translated into the current locale
     */
    public function getLabels(): array;

    /**
     * Get every known event's description, in the order the events were registered
     *
     * @return list<KnownEventInterface>
     */
    public function getEvents(): array;

    /**
     * Whether an event name is known
     *
     * @param string $eventName
     * @return bool
     */
    public function isKnown(string $eventName): bool;

    /**
     * Whether an event is known and detected in the shopper's browser
     *
     * Only such an event may reach the server by the browser posting it; an unknown event never is.
     *
     * @param string $eventName
     * @return bool
     */
    public function isBrowserOriginated(string $eventName): bool;
}
