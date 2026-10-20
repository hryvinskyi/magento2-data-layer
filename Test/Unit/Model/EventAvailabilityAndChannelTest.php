<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Model\Config;
use Hryvinskyi\DataLayer\Model\Config\DraftSettings;
use Hryvinskyi\DataLayer\Model\EventAvailability;
use Hryvinskyi\DataLayer\Model\EventChannelPolicy;
use Hryvinskyi\DataLayer\Model\FixedChannels;
use Hryvinskyi\DataLayer\Model\KnownEvents;
use Hryvinskyi\DataLayerApi\Api\Data\EventChannel;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

/**
 * An event is reported when the store has the data layer on and has not disabled it; it goes to the channel its
 * store routes it to, or the browser
 */
class EventAvailabilityAndChannelTest extends TestCase
{
    /**
     * Every event is available unless the store disabled it; nothing is when the data layer is off
     *
     * @return void
     */
    public function testEventIsAvailableUnlessDisabled(): void
    {
        $on = new EventAvailability($this->config([
            'hryvinskyi_datalayer/general/enabled' => '1',
            'hryvinskyi_datalayer/events/disabled' => 'login,purchase',
        ]));
        $off = new EventAvailability($this->config(['hryvinskyi_datalayer/general/enabled' => '0']));

        self::assertTrue($on->isEnabled('add_to_cart'));
        self::assertTrue($on->isEnabled('generate_lead'));
        self::assertFalse($on->isEnabled('purchase'));
        self::assertFalse($on->isEnabled('login'));
        self::assertFalse($off->isEnabled('add_to_cart'));
    }

    /**
     * An event without a row goes to the browser; a row routes it to the server or to both
     *
     * @return void
     */
    public function testRoutedEventsGoWhereTheirRowSays(): void
    {
        $policy = $this->policy([
            ['event' => 'purchase', 'channel' => 'server'],
            ['event' => 'add_to_cart', 'channel' => 'both'],
        ]);

        self::assertSame(EventChannel::Server, $policy->channelOf('purchase'));
        self::assertSame(EventChannel::Both, $policy->channelOf('add_to_cart'));
        self::assertSame(EventChannel::Browser, $policy->channelOf('login'));
    }

    /**
     * Rows naming an unknown event or an unknown channel are ignored
     *
     * @return void
     */
    public function testJunkRowsAreIgnored(): void
    {
        $policy = $this->policy([
            ['event' => 'purchase', 'channel' => 'pigeon'],
            ['event' => 'unknown_event', 'channel' => 'server'],
            ['event' => 'login', 'channel' => 'server'],
            ['event' => 'login', 'channel' => 'sideways'],
        ]);

        self::assertSame(EventChannel::Browser, $policy->channelOf('purchase'));
        self::assertSame(EventChannel::Browser, $policy->channelOf('unknown_event'));
        self::assertSame(EventChannel::Server, $policy->channelOf('login'));
    }

    /**
     * When two rows name the same event the later one wins
     *
     * @return void
     */
    public function testLaterRowWins(): void
    {
        $policy = $this->policy([
            ['event' => 'purchase', 'channel' => 'server'],
            ['event' => 'purchase', 'channel' => 'both'],
        ]);

        self::assertSame(EventChannel::Both, $policy->channelOf('purchase'));
    }

    /**
     * A known-event list refuses a registered name that is not an event name
     *
     * @return void
     */
    public function testKnownEventsRefuseBadNames(): void
    {
        $known = new KnownEvents(new KnownEventFactoryStub(), ['purchase' => 'Purchase']);
        self::assertTrue($known->isKnown('purchase'));
        self::assertFalse($known->isKnown('refund'));
        self::assertSame(['purchase' => 'Purchase'], $known->getLabels());

        $this->expectException(\InvalidArgumentException::class);
        new KnownEvents(new KnownEventFactoryStub(), ['Bad Name' => 'Bad']);
    }

    /**
     * A channel policy over the given routing rows, knowing purchase, add_to_cart and login
     *
     * @param list<array{event: string, channel: string}> $rows
     * @return EventChannelPolicy
     */
    private function policy(array $rows): EventChannelPolicy
    {
        return new EventChannelPolicy(
            $this->config(['hryvinskyi_datalayer/channels/routing' => json_encode($rows)]),
            new KnownEvents(
                new KnownEventFactoryStub(),
                ['purchase' => 'Purchase', 'add_to_cart' => 'Add to cart', 'login' => 'Login']
            ),
            new FixedChannels()
        );
    }

    /**
     * Settings over the given stored values
     *
     * @param array<string,mixed> $values
     * @return Config
     */
    private function config(array $values): Config
    {
        return new Config(new FakeScopeConfig($values), new Json(), new DraftSettings());
    }
}
