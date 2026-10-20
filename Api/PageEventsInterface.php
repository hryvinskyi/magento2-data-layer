<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

/**
 * Collects the events of a page's sources into the objects pushed to `dataLayer`
 */
interface PageEventsInterface
{
    /**
     * Run every source and every event through the modifiers
     *
     * A source that fails is logged and contributes nothing; an event a modifier drops is left out.
     *
     * @param array<PageEventSourceInterface> $sources In the order their events are pushed
     * @return list<array<string, mixed>> The data layer objects, in source order
     */
    public function collect(array $sources): array;
}
