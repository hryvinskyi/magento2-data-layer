<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

/**
 * Remembers, for the visitor's session, which orders a once-per-order success page event (`purchase`, or the
 * conversion that follows it) was already reported for
 *
 * A reload of the success page, or a later page that shows the same order, does not report it a second time. Only
 * the most recent orders are remembered, so the session stays small.
 */
interface PurchaseLedgerInterface
{
    /**
     * Whether the order was reported in this session
     *
     * @param string $incrementId The order's increment id
     * @return bool
     */
    public function wasReported(string $incrementId): bool;

    /**
     * Remember that the order was reported in this session
     *
     * @param string $incrementId The order's increment id
     * @return void
     */
    public function markReported(string $incrementId): void;
}
