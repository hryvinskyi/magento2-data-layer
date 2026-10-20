<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Api\EventModifierPipelineInterface;
use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Model\Data\VisitorContext;
use Hryvinskyi\DataLayer\Model\EventDispatcher;
use Hryvinskyi\DataLayerApi\Api\Data\EventChannel;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\VisitorContextInterface;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Hryvinskyi\DataLayerApi\Api\EventChannelPolicyInterface;
use Hryvinskyi\DataLayerApi\Api\ServerEventQueueInterface;
use Hryvinskyi\DataLayerApi\Api\ServerEventSinkInterface;
use Hryvinskyi\DataLayerApi\Api\VisitorContextProviderInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * The dispatcher drops disabled events, runs the modifiers, and routes by channel: the browser queue only inside a
 * storefront request, the sinks each on their own; a browser event is forwarded to the sinks without the modifiers;
 * nothing it meets escapes it
 */
class EventDispatcherTest extends TestCase
{
    /**
     * @var list<EventInterface>
     */
    private array $queued = [];

    /**
     * @var list<array{EventInterface, int, ?VisitorContextInterface, ?string}>
     */
    private array $sunk = [];

    /**
     * @var list<string>
     */
    private array $logged = [];

    /**
     * Where an event goes for each channel and request area
     *
     * @dataProvider routingTable
     * @param EventChannel $channel
     * @param string|null $area Null when the request has no area
     * @param bool $toBrowser
     * @param bool $toServer
     * @return void
     */
    #[DataProvider('routingTable')]
    public function testEventGoesWhereItsChannelAndTheAreaAllow(
        EventChannel $channel,
        ?string $area,
        bool $toBrowser,
        bool $toServer
    ): void {
        $dispatcher = $this->dispatcher(channel: $channel, area: $area);

        $dispatcher->dispatch(new Event('add_to_cart', true), 1);

        self::assertCount($toBrowser ? 1 : 0, $this->queued);
        self::assertCount($toServer ? 1 : 0, $this->sunk);
        self::assertSame([], $this->logged);
    }

    /**
     * Every channel crossed with the storefront and non-storefront areas
     *
     * @return array<string, array{EventChannel, string|null, bool, bool}>
     */
    public static function routingTable(): array
    {
        return [
            'browser, storefront' => [EventChannel::Browser, 'frontend', true, false],
            'browser, storefront REST' => [EventChannel::Browser, 'webapi_rest', true, false],
            'browser, admin' => [EventChannel::Browser, 'adminhtml', false, false],
            'browser, cron' => [EventChannel::Browser, 'crontab', false, false],
            'browser, no area' => [EventChannel::Browser, null, false, false],
            'server, storefront' => [EventChannel::Server, 'frontend', false, true],
            'server, admin' => [EventChannel::Server, 'adminhtml', false, true],
            'server, no area' => [EventChannel::Server, null, false, true],
            'both, storefront' => [EventChannel::Both, 'frontend', true, true],
            'both, storefront REST' => [EventChannel::Both, 'webapi_rest', true, true],
            'both, cron' => [EventChannel::Both, 'crontab', false, true],
        ];
    }

    /**
     * A disabled event reaches neither the modifiers nor any destination
     *
     * @return void
     */
    public function testDisabledEventIsDropped(): void
    {
        $modifiers = $this->createMock(EventModifierPipelineInterface::class);
        $modifiers->expects(self::never())->method('apply');

        $dispatcher = $this->dispatcher(channel: EventChannel::Both, enabled: false, modifiers: $modifiers);
        $dispatcher->dispatch(new Event('add_to_cart', true), 1);
        $dispatcher->dispatchToServer(new Event('purchase', true), 1, null, 'purchase:1');

        self::assertSame([], $this->queued);
        self::assertSame([], $this->sunk);
    }

    /**
     * An event a modifier drops goes nowhere; a modified event is what is delivered
     *
     * @return void
     */
    public function testModifiersDecideWhatIsDelivered(): void
    {
        $dropping = $this->createMock(EventModifierPipelineInterface::class);
        $dropping->method('apply')->willReturn(null);
        $this->dispatcher(channel: EventChannel::Both, modifiers: $dropping)
            ->dispatch(new Event('add_to_cart', true), 1);
        self::assertSame([], $this->queued);
        self::assertSame([], $this->sunk);

        $replacement = new Event('add_to_cart', true, ['currency' => 'GBP']);
        $replacing = $this->createMock(EventModifierPipelineInterface::class);
        $replacing->method('apply')->willReturn($replacement);
        $this->dispatcher(channel: EventChannel::Both, modifiers: $replacing)
            ->dispatch(new Event('add_to_cart', true), 1);
        self::assertSame([$replacement], $this->queued);
        self::assertSame($replacement, $this->sunk()[0][0]);
    }

    /**
     * A sink that throws is logged and the other sinks still receive the event
     *
     * @return void
     */
    public function testThrowingSinkIsIsolated(): void
    {
        $failing = $this->createMock(ServerEventSinkInterface::class);
        $failing->method('accept')->willThrowException(new \RuntimeException('outbox down'));

        $dispatcher = $this->dispatcher(channel: EventChannel::Server, sinks: ['failing' => $failing]);
        $dispatcher->dispatch(new Event('add_to_cart', true), 1);

        self::assertCount(1, $this->sunk);
        self::assertCount(1, $this->logged);
        self::assertStringContainsString('outbox down', $this->logged[0]);
    }

    /**
     * A visitor provider that throws is logged and the sinks receive the event without a visitor
     *
     * @return void
     */
    public function testThrowingVisitorProviderNeverEscapes(): void
    {
        $visitors = $this->createMock(VisitorContextProviderInterface::class);
        $visitors->method('current')->willThrowException(new \LogicException('cookie jar broken'));

        $this->dispatcher(channel: EventChannel::Server, visitors: $visitors)
            ->dispatch(new Event('add_to_cart', true), 1);

        self::assertCount(1, $this->sunk);
        self::assertNull($this->sunk[0][2]);
        self::assertCount(1, $this->logged);
    }

    /**
     * A modifier, a channel policy or a queue that throws is logged and never escapes
     *
     * @return void
     */
    public function testThrowingCollaboratorsNeverEscape(): void
    {
        $modifiers = $this->createMock(EventModifierPipelineInterface::class);
        $modifiers->method('apply')->willThrowException(new \Error('modifier bug'));
        $this->dispatcher(channel: EventChannel::Both, modifiers: $modifiers)
            ->dispatch(new Event('add_to_cart', true), 1);
        $this->dispatcher(channel: EventChannel::Both, modifiers: $modifiers)
            ->dispatchToServer(new Event('purchase', true), 1);

        $channels = $this->createMock(EventChannelPolicyInterface::class);
        $channels->method('channelOf')->willThrowException(new \RuntimeException('routing unreadable'));
        $this->dispatcher(channels: $channels)->dispatch(new Event('add_to_cart', true), 1);

        $queue = $this->createMock(ServerEventQueueInterface::class);
        $queue->method('add')->willThrowException(new \RuntimeException('session closed'));
        $this->dispatcher(channel: EventChannel::Both, queue: $queue)->dispatch(new Event('add_to_cart', true), 1);

        self::assertCount(4, $this->logged);
        self::assertCount(1, $this->sunk, 'the sinks still run when the browser queue fails');
    }

    /**
     * The sinks receive the given visitor, or the current one when none is given
     *
     * @return void
     */
    public function testSinksReceiveTheGivenOrTheCurrentVisitor(): void
    {
        $current = new VisitorContext(new \DateTimeImmutable(), 'current');
        $given = new VisitorContext(new \DateTimeImmutable(), 'given');
        $visitors = $this->createMock(VisitorContextProviderInterface::class);
        $visitors->method('current')->willReturn($current);
        $dispatcher = $this->dispatcher(channel: EventChannel::Server, visitors: $visitors);

        $dispatcher->dispatch(new Event('add_to_cart', true), 1);
        $dispatcher->dispatch(new Event('add_to_cart', true), 1, $given);

        self::assertSame($current, $this->sunk[0][2]);
        self::assertSame($given, $this->sunk[1][2]);
    }

    /**
     * Sending to the server only: the de-duplication key reaches every sink unchanged and the browser queue is never
     * touched; dispatching passes no key
     *
     * @return void
     */
    public function testServerOnlyDispatchPassesTheKeyThroughAndSkipsTheBrowser(): void
    {
        $second = $this->recordingSink();
        $dispatcher = $this->dispatcher(channel: EventChannel::Both, area: 'frontend', sinks: ['second' => $second]);

        $dispatcher->dispatchToServer(new Event('purchase', true), 2, null, 'purchase:000000123');

        self::assertSame([], $this->queued);
        self::assertCount(2, $this->sunk);
        self::assertSame('purchase:000000123', $this->sunk[0][3]);
        self::assertSame('purchase:000000123', $this->sunk[1][3]);
        self::assertSame(2, $this->sunk[0][1]);

        $dispatcher->dispatch(new Event('add_to_cart', true), 2);
        self::assertNull($this->sunk()[2][3]);
    }

    /**
     * Sending to the server only does nothing for an event routed to the browser
     *
     * @return void
     */
    public function testServerOnlyDispatchHonoursABrowserChannel(): void
    {
        $this->dispatcher(channel: EventChannel::Browser)->dispatchToServer(new Event('purchase', true), 1, null, 'k');

        self::assertSame([], $this->sunk);
        self::assertSame([], $this->queued);
    }

    /**
     * A browser event is forwarded as the browser reported it: a modifier that would change it again on every run is
     * not applied, the key is passed through, and the browser queue is untouched
     *
     * @return void
     */
    public function testForwardedBrowserEventSkipsTheModifiers(): void
    {
        $appending = $this->createMock(EventModifierPipelineInterface::class);
        $appending->method('apply')->willReturnCallback(
            static function (EventInterface $event): EventInterface {
                $listName = $event->getParameters()['item_list_name'] ?? '';

                return new Event(
                    $event->getName(),
                    true,
                    ['item_list_name' => (is_string($listName) ? $listName : '') . ' (modified)']
                );
            }
        );
        $browserEvent = new Event('view_item_list', true, ['item_list_name' => 'Category (modified)']);

        $this->dispatcher(channel: EventChannel::Both, modifiers: $appending)
            ->forward($browserEvent, 4, null, 'collect:1.2:abc');

        self::assertSame([], $this->queued);
        self::assertCount(1, $this->sunk);
        self::assertSame($browserEvent, $this->sunk[0][0]);
        self::assertSame(4, $this->sunk[0][1]);
        self::assertSame('collect:1.2:abc', $this->sunk[0][3]);
    }

    /**
     * A forwarded event still honours its store view: a disabled event and one routed to the browser only reach no
     * sink, and a failing channel policy is logged, not thrown
     *
     * @return void
     */
    public function testForwardedBrowserEventHonoursAvailabilityAndChannel(): void
    {
        $this->dispatcher(channel: EventChannel::Both, enabled: false)->forward(new Event('select_item', true), 1);
        $this->dispatcher(channel: EventChannel::Browser)->forward(new Event('select_item', true), 1);
        $failing = $this->createMock(EventChannelPolicyInterface::class);
        $failing->method('channelOf')->willThrowException(new \RuntimeException('routing is gone'));
        $this->dispatcher(channels: $failing)->forward(new Event('select_item', true), 1);

        self::assertSame([], $this->sunk);
        self::assertSame([], $this->queued);
        self::assertCount(1, $this->logged);
        self::assertStringContainsString('could not be forwarded', $this->logged[0]);
    }

    /**
     * A sink that is not a server event sink is refused at construction
     *
     * @return void
     */
    public function testForeignSinkIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->dispatcher(sinks: ['wrong' => new \stdClass()]);
    }

    /**
     * Build a dispatcher whose collaborators record into this test
     *
     * @param EventChannel $channel The channel every event is routed to
     * @param string|null $area The request's area; null when it has none
     * @param bool $enabled Whether every event is enabled
     * @param EventModifierPipelineInterface|null $modifiers Null for one that passes events unchanged
     * @param EventChannelPolicyInterface|null $channels Null for one that answers $channel
     * @param ServerEventQueueInterface|null $queue Null for one that records
     * @param VisitorContextProviderInterface|null $visitors Null for one that knows nobody
     * @param array<mixed> $sinks Sinks added after one recording sink
     * @return EventDispatcher
     */
    private function dispatcher(
        EventChannel $channel = EventChannel::Browser,
        ?string $area = 'frontend',
        bool $enabled = true,
        ?EventModifierPipelineInterface $modifiers = null,
        ?EventChannelPolicyInterface $channels = null,
        ?ServerEventQueueInterface $queue = null,
        ?VisitorContextProviderInterface $visitors = null,
        array $sinks = []
    ): EventDispatcher {
        $availability = $this->createMock(EventAvailabilityInterface::class);
        $availability->method('isEnabled')->willReturn($enabled);

        if ($modifiers === null) {
            $modifiers = $this->createMock(EventModifierPipelineInterface::class);
            $modifiers->method('apply')->willReturnArgument(0);
        }
        if ($channels === null) {
            $channels = $this->createMock(EventChannelPolicyInterface::class);
            $channels->method('channelOf')->willReturn($channel);
        }
        if ($queue === null) {
            $queue = $this->createMock(ServerEventQueueInterface::class);
            $queue->method('add')->willReturnCallback(function (EventInterface $event): void {
                $this->queued[] = $event;
            });
        }
        if ($visitors === null) {
            $visitors = $this->createMock(VisitorContextProviderInterface::class);
            $visitors->method('current')->willReturn(null);
        }

        $state = $this->createMock(State::class);
        if ($area === null) {
            $state->method('getAreaCode')->willThrowException(new LocalizedException(__('Area code is not set')));
        } else {
            $state->method('getAreaCode')->willReturn($area);
        }

        $test = $this;
        $logger = new class ($test) extends AbstractLogger {
            /**
             * @param EventDispatcherTest $test
             */
            public function __construct(private readonly EventDispatcherTest $test)
            {
            }

            /**
             * @inheritDoc
             */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->test->recordLog((string) $message);
            }
        };

        return new EventDispatcher(
            $availability,
            $modifiers,
            $channels,
            $queue,
            $visitors,
            $state,
            $logger,
            ['recording' => $this->recordingSink()] + $sinks
        );
    }

    /**
     * Record a logged message
     *
     * @param string $message
     * @return void
     */
    public function recordLog(string $message): void
    {
        $this->logged[] = $message;
    }

    /**
     * Get what the sinks received so far
     *
     * @return list<array{EventInterface, int, ?VisitorContextInterface, ?string}>
     */
    private function sunk(): array
    {
        return $this->sunk;
    }

    /**
     * A sink that records what it receives into this test
     *
     * @return ServerEventSinkInterface
     */
    private function recordingSink(): ServerEventSinkInterface
    {
        $sink = $this->createMock(ServerEventSinkInterface::class);
        $sink->method('accept')->willReturnCallback(
            function (EventInterface $event, int $storeId, ?VisitorContextInterface $visitor, ?string $key): void {
                $this->sunk[] = [$event, $storeId, $visitor, $key];
            }
        );

        return $sink;
    }
}
