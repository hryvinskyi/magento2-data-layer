<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

/**
 * The price-visibility answer forced for the running request, which price visibility gives before asking anyone
 *
 * @api
 */
interface PriceVisibilityOverrideReaderInterface
{
    /**
     * Get the forced answer
     *
     * The answer changes while work runs under an override, so it is read afresh on every call.
     *
     * @return bool|null Null when no answer is forced
     * @phpstan-impure
     */
    public function forced(): ?bool;
}
