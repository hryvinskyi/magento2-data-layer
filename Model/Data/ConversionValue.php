<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Data;

use Hryvinskyi\DataLayer\Api\Data\ConversionValueInterface;

/**
 * A conversion's amount and currency, valid by construction
 *
 * A negative, infinite or not-a-number amount and a currency that is not three capital letters are refused when the
 * value is created, so no conversion can report money that is not money.
 */
class ConversionValue implements ConversionValueInterface
{
    private const CURRENCY_PATTERN = '/^[A-Z]{3}$/D';

    /**
     * @var float
     */
    private readonly float $amount;

    /**
     * @param float $amount
     * @param string $currency
     * @throws \InvalidArgumentException When the amount is negative or not finite, or the currency is not a code
     */
    public function __construct(
        float $amount,
        private readonly string $currency
    ) {
        if (!is_finite($amount) || $amount < 0.0) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a conversion amount.', (string) $amount));
        }
        if (preg_match(self::CURRENCY_PATTERN, $currency) !== 1) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a currency code.', $currency));
        }
        $this->amount = round($amount, 2);
    }

    /**
     * @inheritDoc
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @inheritDoc
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }
}
