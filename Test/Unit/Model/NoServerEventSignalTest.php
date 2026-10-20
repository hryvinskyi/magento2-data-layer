<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Model\NoServerEventSignal;
use Hryvinskyi\DataLayer\Model\ServerEventQueue;
use Hryvinskyi\DataLayer\Test\Unit\Model\Session\FakeVisitorSession;
use PHPUnit\Framework\TestCase;

/**
 * Without a module that delivers events to the browser, nothing is signalled and the queue still works
 */
class NoServerEventSignalTest extends TestCase
{
    /**
     * No response can carry the signal, and giving or clearing it changes nothing
     *
     * @return void
     */
    public function testSignalsNothing(): void
    {
        $signal = new NoServerEventSignal();

        $signal->signal();
        $signal->clear();

        self::assertFalse($signal->canSignal());
    }

    /**
     * A queue built with the null signal still keeps and hands over the browser's events, logging nothing
     *
     * @return void
     */
    public function testQueueWorksWithoutASignal(): void
    {
        $logger = new RecordingLogger();
        $queue = new ServerEventQueue(
            new FakeVisitorSession(),
            new NoServerEventSignal(),
            $logger,
            new FixedDateTime()
        );

        $queue->add(new Event('login', false, ['method' => 'website']));

        self::assertTrue($queue->hasEvents());
        $waiting = $queue->pending();
        self::assertSame([['event' => 'login', 'method' => 'website']], array_column($waiting, 'event'));
        $queue->acknowledge(array_column($waiting, 'id'));
        self::assertFalse($queue->hasEvents());
        self::assertSame([], $logger->records);
    }
}
