<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;

/**
 * An event is reported when the data layer is on for the store view and the store has not disabled the event
 *
 * Disabling works by exclusion: an event nobody listed is reported, so an event added later is on without a
 * configuration change.
 */
class EventAvailability implements EventAvailabilityInterface
{
    /**
     * @param ConfigInterface $config
     */
    public function __construct(private readonly ConfigInterface $config)
    {
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(string $eventName, ?int $storeId = null): bool
    {
        return $this->config->isEnabled($storeId)
            && !in_array($eventName, $this->config->getDisabledEvents($storeId), true);
    }
}
