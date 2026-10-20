<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Api\ServerEventSignalInterface;

/**
 * Records the signals given to the browser, in order
 */
class RecordingSignal implements ServerEventSignalInterface
{
    /**
     * @var list<string>
     */
    public array $calls = [];

    /**
     * @param bool $canSignal Whether the response can carry the signal
     */
    public function __construct(private readonly bool $canSignal = true)
    {
    }

    /**
     * @inheritDoc
     */
    public function canSignal(): bool
    {
        return $this->canSignal;
    }

    /**
     * @inheritDoc
     */
    public function signal(): void
    {
        $this->calls[] = 'signal';
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $this->calls[] = 'clear';
    }
}
