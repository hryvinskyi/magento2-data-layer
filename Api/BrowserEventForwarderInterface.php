<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\VisitorContextInterface;

/**
 * Sends an event a visitor's browser already reported to the server sinks, as the browser reported it
 *
 * A browser event was built, and went through the event modifiers, when its page or list was printed. Running the
 * modifiers again would change it twice (a modifier need not give the same result on its own output), so the event is
 * forwarded as it is: it is still dropped when its store view disables it, and reaches the sinks only when the store
 * view's channel for it includes the server. The browser queue is never touched.
 */
interface BrowserEventForwarderInterface
{
    /**
     * Forward a browser event to every server sink, without running the modifiers again
     *
     * Never throws; a failure is logged.
     *
     * @param EventInterface $event The event as the browser reported it
     * @param int $storeId The store view the event belongs to
     * @param VisitorContextInterface|null $visitor Who the event is about; null for the current request's visitor
     * @param string|null $deduplicationKey The fact the event reports once, passed to every sink as given
     * @return void
     */
    public function forward(
        EventInterface $event,
        int $storeId,
        ?VisitorContextInterface $visitor = null,
        ?string $deduplicationKey = null
    ): void;
}
