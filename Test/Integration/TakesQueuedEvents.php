<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration;

use Hryvinskyi\DataLayer\Api\ServerEventDrainInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Takes the events waiting for the browser as a page does: reads them, then acknowledges every id it read
 */
trait TakesQueuedEvents
{
    /**
     * Read the waiting events and acknowledge them, so the queue no longer holds them
     *
     * @return list<array<string, mixed>> The events, oldest first, as pushed to the data layer
     */
    private function takeQueuedEvents(): array
    {
        $queue = Bootstrap::getObjectManager()->get(ServerEventDrainInterface::class);
        if (!$queue instanceof ServerEventDrainInterface) {
            throw new \LogicException('The container returned no server event drain.');
        }
        $waiting = $queue->pending();
        $queue->acknowledge(array_column($waiting, 'id'));

        return array_column($waiting, 'event');
    }
}
