<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\FixedChannelsInterface;
use Hryvinskyi\DataLayer\Api\KnownEventsInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventChannel;
use Hryvinskyi\DataLayerApi\Api\EventChannelPolicyInterface;

/**
 * Routes each event by its fixed channel, else by the store view's routing rows
 *
 * An event whose channel is fixed goes there whatever the rows say. Otherwise a row whose event is not known or whose
 * channel does not exist is ignored; an event without a usable row goes to the browser. When two rows name the same
 * event, the later one wins.
 */
class EventChannelPolicy implements EventChannelPolicyInterface
{
    /**
     * @param ConfigInterface $config
     * @param KnownEventsInterface $knownEvents
     * @param FixedChannelsInterface $fixedChannels
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly KnownEventsInterface $knownEvents,
        private readonly FixedChannelsInterface $fixedChannels
    ) {
    }

    /**
     * @inheritDoc
     */
    public function channelOf(string $eventName, ?int $storeId = null): EventChannel
    {
        $fixed = $this->fixedChannels->get()[$eventName] ?? null;
        if ($fixed !== null) {
            return $fixed;
        }

        $channel = EventChannel::Browser;
        foreach ($this->config->getChannelRouting($storeId) as $row) {
            $routed = EventChannel::tryFrom($row['channel']);
            if ($row['event'] === $eventName && $routed !== null && $this->knownEvents->isKnown($eventName)) {
                $channel = $routed;
            }
        }

        return $channel;
    }
}
