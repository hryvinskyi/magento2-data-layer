<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\ServerEventSignalInterface;

/**
 * Signals nothing: what a store has while no module delivers events to the browser
 *
 * No response carries this signal, so giving and clearing it record nothing. The queue still keeps the browser's
 * events, and a module that delivers them to the browser replaces this signal.
 */
class NoServerEventSignal implements ServerEventSignalInterface
{
    /**
     * @inheritDoc
     */
    public function canSignal(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function signal(): void
    {
        if (!$this->canSignal()) {
            return;
        }
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        if (!$this->canSignal()) {
            return;
        }
    }
}
