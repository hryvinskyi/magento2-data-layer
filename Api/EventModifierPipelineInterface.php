<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;

/**
 * Applies every event modifier to an event before it is delivered
 */
interface EventModifierPipelineInterface
{
    /**
     * Run the event through every modifier in their configured order
     *
     * @param EventInterface $event
     * @return EventInterface|null The event as the last modifier left it; null when a modifier dropped it, in which
     *     case no later modifier sees it
     */
    public function apply(EventInterface $event): ?EventInterface;
}
