<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\PurchaseLedgerInterface;
use Hryvinskyi\DataLayer\Api\Session\VisitorSessionInterface;

/**
 * Keeps the increment ids of the last orders reported on a success page in the data layer's own session namespace
 *
 * At most the twenty most recent orders are kept: a visitor never looks at more success pages than that in one
 * session, and the list never grows the session without bound. Each once-per-order event keeps its own list under its
 * own session key (`purchase` under the default one), so reporting one event never marks another as reported.
 */
class PurchaseLedger implements PurchaseLedgerInterface
{
    public const CAPACITY = 20;

    /**
     * @param VisitorSessionInterface $session The data layer's own session namespace
     * @param string $key The session key of this ledger's list
     */
    public function __construct(
        private readonly VisitorSessionInterface $session,
        private readonly string $key = 'reported_purchases'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function wasReported(string $incrementId): bool
    {
        return in_array($incrementId, $this->reported(), true);
    }

    /**
     * @inheritDoc
     */
    public function markReported(string $incrementId): void
    {
        $reported = array_values(array_diff($this->reported(), [$incrementId]));
        $reported[] = $incrementId;

        $this->session->set($this->key, array_slice($reported, -self::CAPACITY));
    }

    /**
     * Get the remembered increment ids, oldest first
     *
     * @return list<string>
     */
    private function reported(): array
    {
        $stored = $this->session->get($this->key);
        if (!is_array($stored)) {
            return [];
        }

        return array_values(array_filter($stored, 'is_string'));
    }
}
