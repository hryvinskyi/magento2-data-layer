<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Data;

/**
 * The value a conversion reports: an amount and the currency it is in, which never travel apart
 *
 * The amount is a finite number of zero or more, rounded to two decimals; the currency is a three-letter ISO 4217
 * code in capitals.
 */
interface ConversionValueInterface
{
    /**
     * Get the amount
     *
     * @return float
     */
    public function getAmount(): float;

    /**
     * Get the currency code
     *
     * @return string
     */
    public function getCurrency(): string;
}
