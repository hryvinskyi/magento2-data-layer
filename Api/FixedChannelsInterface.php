<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

use Hryvinskyi\DataLayerApi\Api\Data\EventChannel;

/**
 * The events whose channel is fixed by the module that reports them, whatever a store view's routing says
 *
 * An event that can only happen on one side has nowhere else to go: a refund is recorded in the admin or by a payment
 * callback, never in the shopper's browser, so it is always sent from the server. The module that adds such an event
 * registers its channel here.
 */
interface FixedChannelsInterface
{
    /**
     * Get the fixed channel of every event that has one
     *
     * @return array<string, EventChannel> Keyed by GA4 event name
     */
    public function get(): array;
}
