<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\EventModifierPipelineInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\EventModifierInterface;

/**
 * Runs the modifiers registered through the `modifiers` argument, in the order the object manager hands them over
 *
 * The object manager sorts the argument's items by their `sortOrder` attribute; items without one keep merge order.
 * The first modifier that drops the event ends the run.
 */
class EventModifierPipeline implements EventModifierPipelineInterface
{
    /**
     * @var list<EventModifierInterface>
     */
    private readonly array $modifiers;

    /**
     * @param array<mixed> $modifiers Each an EventModifierInterface, in the order they apply
     * @throws \InvalidArgumentException When a modifier is not an EventModifierInterface
     */
    public function __construct(array $modifiers = [])
    {
        $accepted = [];
        foreach ($modifiers as $name => $modifier) {
            if (!$modifier instanceof EventModifierInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'The event modifier "%s" must implement %s.',
                    (string) $name,
                    EventModifierInterface::class
                ));
            }
            $accepted[] = $modifier;
        }
        $this->modifiers = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function apply(EventInterface $event): ?EventInterface
    {
        foreach ($this->modifiers as $modifier) {
            $event = $modifier->modify($event);
            if ($event === null) {
                return null;
            }
        }

        return $event;
    }
}
