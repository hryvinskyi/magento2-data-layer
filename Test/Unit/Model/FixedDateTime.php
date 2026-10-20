<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * A clock whose time the test sets and moves
 */
class FixedDateTime extends DateTime
{
    /**
     * @param int $now Seconds since the epoch
     */
    public function __construct(public int $now = 1_800_000_000)
    {
    }

    /**
     * Get the time the test set, whatever is asked
     *
     * @param mixed $input
     * @return int
     */
    public function gmtTimestamp($input = null): int
    {
        return $this->now;
    }
}
