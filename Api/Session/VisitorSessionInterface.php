<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Session;

/**
 * What the data layer keeps for the visitor between requests, in its own session namespace
 *
 * Values are plain data (arrays and scalars), never objects: they are serialized with the session.
 */
interface VisitorSessionInterface
{
    /**
     * Read a stored value
     *
     * @param string $key
     * @return mixed Null when nothing is stored under the key
     */
    public function get(string $key): mixed;

    /**
     * Store a value under a key, replacing what was there
     *
     * @param string $key
     * @param array<mixed>|string|int|float|bool $value
     * @return void
     */
    public function set(string $key, array|string|int|float|bool $value): void;

    /**
     * Remove what is stored under a key
     *
     * @param string $key
     * @return void
     */
    public function remove(string $key): void;
}
