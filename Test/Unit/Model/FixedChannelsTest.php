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
use Hryvinskyi\DataLayer\Model\EventChannelPolicy;
use Hryvinskyi\DataLayer\Model\FixedChannels;
use Hryvinskyi\DataLayer\Model\KnownEvents;
use Hryvinskyi\DataLayerApi\Api\Data\EventChannel;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An event with a fixed channel goes there whatever the store's routing rows say; a registration that names no event
 * or no channel is refused when the list is built
 */
class FixedChannelsTest extends TestCase
{
    /**
     * The registered codes come back as channels
     *
     * @return void
     */
    public function testRegisteredChannelsComeBackTyped(): void
    {
        $fixed = new FixedChannels(['refund' => 'server', 'sample_event' => 'both']);

        self::assertSame(['refund' => EventChannel::Server, 'sample_event' => EventChannel::Both], $fixed->get());
        self::assertSame([], (new FixedChannels())->get());
    }

    /**
     * A fixed channel beats a stored row naming the same event, and holds without any row
     *
     * @return void
     */
    public function testFixedChannelBeatsStoredRow(): void
    {
        $policy = new EventChannelPolicy(
            new Config(
                new FakeScopeConfig([
                    'hryvinskyi_datalayer/channels/routing' => json_encode([
                        ['event' => 'refund', 'channel' => 'browser'],
                        ['event' => 'purchase', 'channel' => 'server'],
                    ]),
                ]),
                new Json(),
                new DraftSettings()
            ),
            new KnownEvents(
                new KnownEventFactoryStub(),
                ['purchase' => 'Purchase', 'refund' => 'Refund', 'login' => 'Login']
            ),
            new FixedChannels(['refund' => 'server', 'login' => 'both'])
        );

        self::assertSame(EventChannel::Server, $policy->channelOf('refund'));
        self::assertSame(EventChannel::Both, $policy->channelOf('login'));
        self::assertSame(EventChannel::Server, $policy->channelOf('purchase'));
    }

    /**
     * A value that is not a channel code is refused
     *
     * @param mixed $value
     * @return void
     */
    #[DataProvider('unknownChannels')]
    public function testUnknownFixedValueThrows(mixed $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The fixed channel of "refund" must be one of: browser, server, both.');

        new FixedChannels(['refund' => $value]);
    }

    /**
     * Values that are not channel codes
     *
     * @return array<string, array{mixed}>
     */
    public static function unknownChannels(): array
    {
        return [
            'unknown code' => ['pigeon'],
            'wrong case' => ['Server'],
            'empty' => [''],
            'not text' => [1],
        ];
    }

    /**
     * A name that is not a GA4 event name is refused
     *
     * @return void
     */
    public function testBadEventNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new FixedChannels(['Not An Event' => 'server']);
    }
}
