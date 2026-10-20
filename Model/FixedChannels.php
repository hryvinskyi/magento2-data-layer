<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\FixedChannelsInterface;
use Hryvinskyi\DataLayer\Model\Data\Ga4Name;
use Hryvinskyi\DataLayerApi\Api\Data\EventChannel;

/**
 * The fixed channels, registered through the `channels` argument (event name to `browser`, `server` or `both`)
 *
 * A registration that names no GA4 event or no existing channel fails when the object is built, so a typo in the
 * wiring cannot silently send an event to the wrong place.
 */
class FixedChannels implements FixedChannelsInterface
{
    /**
     * @var array<string, EventChannel>
     */
    private readonly array $channels;

    /**
     * @param array<mixed> $channels Channel code of each event, keyed by GA4 event name
     * @throws \InvalidArgumentException When a name is not a GA4 event name or a value is not a channel code
     */
    public function __construct(array $channels = [])
    {
        $codes = array_map(static fn (EventChannel $known): string => $known->value, EventChannel::cases());
        $fixed = [];
        foreach ($channels as $name => $code) {
            if (!is_string($name) || preg_match(Ga4Name::PATTERN, $name) !== 1) {
                throw new \InvalidArgumentException(
                    sprintf('"%s" is given a fixed channel but is not a GA4 event name.', (string) $name)
                );
            }
            $channel = is_string($code) ? EventChannel::tryFrom($code) : null;
            if ($channel === null) {
                throw new \InvalidArgumentException(sprintf(
                    'The fixed channel of "%s" must be one of: %s.',
                    $name,
                    implode(', ', $codes)
                ));
            }
            $fixed[$name] = $channel;
        }
        $this->channels = $fixed;
    }

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        return $this->channels;
    }
}
