<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\EventModifierPipelineInterface;
use Hryvinskyi\DataLayer\Api\PageEventsInterface;
use Hryvinskyi\DataLayer\Api\PageEventSourceInterface;
use Psr\Log\LoggerInterface;

/**
 * Runs a page's event sources and turns their events into the objects pushed to `dataLayer`
 *
 * Every event goes through the modifiers before it is printed, as an event built on the server does before it is
 * delivered. A source or a modifier that fails is logged and costs only its own events: the page always renders.
 */
class PageEvents implements PageEventsInterface
{
    /**
     * @param EventModifierPipelineInterface $modifiers
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly EventModifierPipelineInterface $modifiers,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function collect(array $sources): array
    {
        $objects = [];
        foreach ($sources as $name => $source) {
            try {
                $events = $source->events();
            } catch (\Throwable $exception) {
                $this->logger->error('A GA4 page event source failed; its events were left out of the page.', [
                    'source' => is_string($name) ? $name : $source::class,
                    'exception' => $exception,
                ]);
                continue;
            }

            foreach ($events as $event) {
                try {
                    $event = $this->modifiers->apply($event);
                } catch (\Throwable $exception) {
                    $this->logger->error('A GA4 event modifier failed; the page event was left out.', [
                        'event' => $event->getName(),
                        'exception' => $exception,
                    ]);
                    continue;
                }
                if ($event !== null) {
                    $objects[] = $event->toDataLayer();
                }
            }
        }

        return $objects;
    }
}
