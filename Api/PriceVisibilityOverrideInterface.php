<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

/**
 * Runs work as a visitor who sees prices, or as one who may not
 *
 * Which visitors may not see a price is decided by the modules that contribute to price visibility, so the data layer
 * cannot pick such a visitor itself. While the work runs, price visibility gives the forced answer for every product
 * without asking a contribution; the answer is cleared when the work returns or throws. One answer describes one
 * visitor, so an override is never opened inside another.
 *
 * @api
 */
interface PriceVisibilityOverrideInterface
{
    /**
     * Run work while every price reads as visible, or as hidden
     *
     * @template T
     * @param bool $visible The answer price visibility gives while the work runs
     * @param callable $work
     * @phpstan-param callable(): T $work
     * @return T What the work returned
     * @throws \LogicException When an answer is already forced
     */
    public function withAnswer(bool $visible, callable $work): mixed;
}
