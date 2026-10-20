<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

/**
 * Hands the events queued in the visitor's session to the browser in two steps: read, then acknowledge
 *
 * Delivery to the page is at least once. Reading the waiting events leaves them queued; only the ids the browser
 * acknowledges, once it has pushed their events, are removed. An answer the browser never used (the page was left
 * while the request ran) loses nothing: the next read returns the same events with the same ids, and the browser skips
 * the ids it already pushed. An event that waits longer than the queue's age limit is dropped instead of being handed
 * over, so it cannot be pushed on a much later page.
 *
 * The queue lives in the session and relies on Magento's session locking to keep a visitor's requests from writing it
 * at the same time; without locking, the session is saved last-wins and a concurrent request's change can be lost.
 */
interface ServerEventDrainInterface
{
    /**
     * Get every waiting event, oldest first, each with its id, leaving them queued
     *
     * When nothing waits, the browser is told so.
     *
     * @return list<array{id: string, event: array<string, mixed>}>
     */
    public function pending(): array;

    /**
     * Remove the events the browser has pushed, by id
     *
     * Ids the queue does not hold are ignored, so acknowledging twice is harmless. Events queued after the browser
     * read the queue stay for the next read. The browser is told that nothing waits any more, unless events still do.
     *
     * @param list<string> $ids
     * @return void
     */
    public function acknowledge(array $ids): void;

    /**
     * Whether any event waits for the browser, leaving the queue as it is
     *
     * @return bool
     */
    public function hasEvents(): bool;
}
