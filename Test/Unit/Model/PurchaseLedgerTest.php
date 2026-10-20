<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Model\PurchaseLedger;
use Hryvinskyi\DataLayer\Model\Session\VisitorSession;
use PHPUnit\Framework\TestCase;

/**
 * The ledger remembers reported orders in the session, at most the twenty most recent
 */
class PurchaseLedgerTest extends TestCase
{
    /**
     * An order is unknown until marked, and known afterwards, also to a ledger reading the same session later
     *
     * @return void
     */
    public function testRemembersAcrossRequests(): void
    {
        $session = new FakeSession();
        $ledger = new PurchaseLedger(new VisitorSession($session));

        self::assertFalse($ledger->wasReported('100'));
        $ledger->markReported('100');

        self::assertTrue((new PurchaseLedger(new VisitorSession($session)))->wasReported('100'));
        self::assertFalse($ledger->wasReported('101'));
    }

    /**
     * Only the last twenty orders are kept; marking an order again makes it the most recent
     *
     * @return void
     */
    public function testKeepsTheTwentyMostRecent(): void
    {
        $session = new FakeSession();
        $ledger = new PurchaseLedger(new VisitorSession($session));
        for ($i = 1; $i <= 21; $i++) {
            $ledger->markReported((string) $i);
        }

        self::assertFalse($ledger->wasReported('1'));
        self::assertTrue($ledger->wasReported('2'));
        self::assertTrue($ledger->wasReported('21'));

        $ledger->markReported('2');
        $ledger->markReported('22');
        self::assertTrue($ledger->wasReported('2'), 'marked again, so it is recent');
        self::assertFalse($ledger->wasReported('3'));
        $stored = $session->getData('reported_purchases');
        self::assertIsArray($stored);
        self::assertCount(PurchaseLedger::CAPACITY, $stored);
    }

    /**
     * Anything else stored under the ledger's key reads as an empty ledger
     *
     * @return void
     */
    public function testCorruptValueReadsAsEmpty(): void
    {
        $session = new FakeSession();
        $session->setData('reported_purchases', 'garbage');

        self::assertFalse((new PurchaseLedger(new VisitorSession($session)))->wasReported('garbage'));
    }
}
