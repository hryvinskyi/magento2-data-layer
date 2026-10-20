<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Session;

use Hryvinskyi\DataLayer\Api\Session\VisitorSessionInterface;

/**
 * The visitor's session namespace kept in memory
 */
class FakeVisitorSession implements VisitorSessionInterface
{
    /**
     * @var array<string, mixed>
     */
    public array $values = [];

    /**
     * Run once, right after the next read: what another request writes between this request's read and its write
     *
     * @var (callable(): void)|null
     */
    public $afterFirstRead = null;

    /**
     * @inheritDoc
     */
    public function get(string $key): mixed
    {
        $value = $this->values[$key] ?? null;
        $after = $this->afterFirstRead;
        if ($after !== null) {
            $this->afterFirstRead = null;
            $after();
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, array|string|int|float|bool $value): void
    {
        $this->values[$key] = $value;
    }

    /**
     * @inheritDoc
     */
    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }
}
