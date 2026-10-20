<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Config;

/**
 * Runs work against settings that are not saved yet
 *
 * While the work runs, every data layer setting reader answers a drafted path from the draft instead of the stored
 * configuration, so an event can be built, previewed or test-sent with the values a person is still editing. The
 * draft lives only in this request and only for the duration of the work: it is cleared when the work returns or
 * throws. One draft describes one scope, so a draft is never opened inside another.
 *
 * @api
 */
interface DraftSettingsInterface
{
    /**
     * Run work while the given settings read as drafted
     *
     * @template T
     * @param array<string,string> $values Raw values in their stored form, keyed by config path
     *     (`section/group/field`): flags as "1" or "0", choices and text as stored, a secret in plain text
     * @param callable $work
     * @phpstan-param callable(): T $work
     * @return T What the work returned
     * @throws \InvalidArgumentException When a path is not a `section/group/field` config path
     * @throws \LogicException When a draft is already active
     */
    public function withDraft(array $values, callable $work): mixed;
}
