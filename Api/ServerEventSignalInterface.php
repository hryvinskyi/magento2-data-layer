<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

/**
 * Tells the visitor's browser whether events detected on the server wait to be taken
 *
 * signal() and clear() record what the response should say; it is written only on a response the full-page cache will
 * not store.
 */
interface ServerEventSignalInterface
{
    /**
     * Whether the current response can carry the signal
     *
     * @return bool
     */
    public function canSignal(): bool;

    /**
     * Tell the browser that events wait
     *
     * @return void
     */
    public function signal(): void;

    /**
     * Tell the browser that nothing waits any more
     *
     * @return void
     */
    public function clear(): void;
}
