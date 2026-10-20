<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Api\PageEventSourceInterface;
use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Model\EventModifierPipeline;
use Hryvinskyi\DataLayer\Model\PageEvents;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\EventModifierInterface;
use PHPUnit\Framework\TestCase;

/**
 * Every source runs; every event goes through the modifiers; failures cost only their own events
 */
class PageEventsTest extends TestCase
{
    /**
     * Events come out in source order as data layer objects; modifiers change or drop them
     *
     * @return void
     */
    public function testModifiersApplyAndDroppedEventsAreLeftOut(): void
    {
        $logger = new RecordingLogger();
        $pageEvents = new PageEvents(new EventModifierPipeline([
            $this->modifier(static fn (EventInterface $event): ?EventInterface => $event->getName() === 'view_cart'
                ? null
                : $event),
            $this->modifier(static fn (EventInterface $event): EventInterface => new Event(
                $event->getName(),
                true,
                $event->getParameters() + ['currency' => 'EUR']
            )),
        ]), $logger);

        $objects = $pageEvents->collect([
            'first' => $this->source([new Event('view_item', true), new Event('view_cart', true)]),
            'second' => $this->source([new Event('purchase', true, ['value' => 1.5])]),
        ]);

        self::assertSame(
            [
                ['event' => 'view_item', 'ecommerce' => ['currency' => 'EUR']],
                ['event' => 'purchase', 'ecommerce' => ['value' => 1.5, 'currency' => 'EUR']],
            ],
            $objects
        );
        self::assertSame([], $logger->records);
    }

    /**
     * A source that throws is logged and skipped; the others still report
     *
     * @return void
     */
    public function testThrowingSourceIsLoggedAndSkipped(): void
    {
        $logger = new RecordingLogger();
        $failing = $this->createMock(PageEventSourceInterface::class);
        $failing->method('events')->willThrowException(new \RuntimeException('boom'));

        $objects = (new PageEvents(new EventModifierPipeline(), $logger))->collect([
            'broken' => $failing,
            'working' => $this->source([new Event('view_item', true)]),
        ]);

        self::assertSame([['event' => 'view_item', 'ecommerce' => []]], $objects);
        self::assertCount(1, $logger->records);
        self::assertSame('broken', $logger->records[0]['context']['source']);
    }

    /**
     * A modifier that throws costs only the event it was modifying
     *
     * @return void
     */
    public function testThrowingModifierCostsOnlyItsEvent(): void
    {
        $logger = new RecordingLogger();
        $pageEvents = new PageEvents(new EventModifierPipeline([
            $this->modifier(static fn (EventInterface $event): EventInterface => $event->getName() === 'view_cart'
                ? throw new \RuntimeException('boom')
                : $event),
        ]), $logger);

        $objects = $pageEvents->collect([$this->source([new Event('view_cart', true), new Event('view_item', true)])]);

        self::assertSame([['event' => 'view_item', 'ecommerce' => []]], $objects);
        self::assertSame('view_cart', $logger->records[0]['context']['event']);
    }

    /**
     * A source that supplies the given events
     *
     * @param list<EventInterface> $events
     * @return PageEventSourceInterface
     */
    private function source(array $events): PageEventSourceInterface
    {
        $source = $this->createMock(PageEventSourceInterface::class);
        $source->method('events')->willReturn($events);

        return $source;
    }

    /**
     * A modifier doing what the callback does
     *
     * @param callable(EventInterface): ?EventInterface $modify
     * @return EventModifierInterface
     */
    private function modifier(callable $modify): EventModifierInterface
    {
        $modifier = $this->createMock(EventModifierInterface::class);
        $modifier->method('modify')->willReturnCallback($modify);

        return $modifier;
    }
}
