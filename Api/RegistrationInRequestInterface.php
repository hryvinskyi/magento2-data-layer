<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

/**
 * Remembers, for the rest of the current request, that a shopper registered in it
 *
 * A registration signs the new customer in within the same request; knowing that it happened lets the sign-in that
 * follows go unreported, so a registration is reported as a sign-up only.
 */
interface RegistrationInRequestInterface
{
    /**
     * Record that a shopper registered in this request
     *
     * @return void
     */
    public function mark(): void;

    /**
     * Whether a shopper registered earlier in this request
     *
     * @return bool
     */
    public function isMarked(): bool;
}
