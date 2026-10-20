<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Config;

/**
 * The drafted settings a setting reader consults before the stored configuration
 *
 * Outside a draft no path is drafted, so a reader asks the stored configuration exactly as it would without it.
 *
 * @api
 */
interface DraftSettingReaderInterface
{
    /**
     * Whether a config path is drafted right now
     *
     * The answer changes while work runs under a draft, so it is read afresh on every call.
     *
     * @param string $path
     * @return bool
     * @phpstan-impure
     */
    public function has(string $path): bool;

    /**
     * Get a drafted path's raw value, in its stored form
     *
     * @param string $path
     * @return string A secret comes back in plain text, never encrypted
     * @throws \OutOfBoundsException When the path is not drafted
     * @phpstan-impure
     */
    public function value(string $path): string;
}
