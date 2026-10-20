<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Model\EventModifierPipeline;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\EventModifierInterface;
use PHPUnit\Framework\TestCase;

/**
 * Modifiers apply in the order they are handed over, each to the previous one's result, and the first drop ends
 * the run
 */
class EventModifierPipelineTest extends TestCase
{
    /**
     * Without modifiers the event passes unchanged
     *
     * @return void
     */
    public function testEventPassesUnchangedWithoutModifiers(): void
    {
        $event = new Event('login', false);

        self::assertSame($event, (new EventModifierPipeline())->apply($event));
    }

    /**
     * Each modifier sees the previous one's result, in order
     *
     * @return void
     */
    public function testModifiersApplyInOrder(): void
    {
        $pipeline = new EventModifierPipeline([
            'first' => $this->appending('a'),
            'second' => $this->appending('b'),
            'third' => $this->appending('c'),
        ]);

        $result = $pipeline->apply(new Event('login', false, ['trail' => '']));

        self::assertNotNull($result);
        self::assertSame(['trail' => 'abc'], $result->getParameters());
    }

    /**
     * A modifier that drops the event ends the run: later modifiers never see it
     *
     * @return void
     */
    public function testDroppedEventReachesNoLaterModifier(): void
    {
        $later = $this->createMock(EventModifierInterface::class);
        $later->expects(self::never())->method('modify');
        $dropping = $this->createMock(EventModifierInterface::class);
        $dropping->method('modify')->willReturn(null);

        $pipeline = new EventModifierPipeline(
            ['first' => $this->appending('a'), 'drop' => $dropping, 'later' => $later]
        );

        self::assertNull($pipeline->apply(new Event('login', false, ['trail' => ''])));
    }

    /**
     * A modifier that is not an event modifier is refused at construction
     *
     * @return void
     */
    public function testForeignModifierIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EventModifierPipeline(['wrong' => new \stdClass()]);
    }

    /**
     * A modifier that appends a letter to the event's `trail` parameter
     *
     * @param string $letter
     * @return EventModifierInterface
     */
    private function appending(string $letter): EventModifierInterface
    {
        return new class ($letter) implements EventModifierInterface {
            /**
             * @param string $letter
             */
            public function __construct(private readonly string $letter)
            {
            }

            /**
             * @inheritDoc
             */
            public function modify(EventInterface $event): EventInterface
            {
                $trail = $event->getParameters()['trail'] ?? '';

                return new Event(
                    $event->getName(),
                    $event->isEcommerce(),
                    ['trail' => (is_string($trail) ? $trail : '') . $this->letter]
                );
            }
        };
    }
}
