<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Observer;

use Hryvinskyi\DataLayer\Model\Cart\RepricedLines;
use Hryvinskyi\DataLayer\Observer\MarkCartTotalsCollected;
use Hryvinskyi\DataLayer\Observer\MarkRepricedCartLines;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use PHPUnit\Framework\TestCase;

/**
 * Every line of an addition, children included, awaits its cart's totals until they run; another cart's totals
 * leave it waiting
 */
class RepricedLineObserversTest extends TestCase
{
    /**
     * An addition marks each of its lines; the totals of their cart release them, another cart's do not
     *
     * @return void
     */
    public function testLinesAwaitTheirCartTotals(): void
    {
        $repriced = new RepricedLines();
        $parent = $this->createMock(QuoteItem::class);
        $child = $this->createMock(QuoteItem::class);
        $untouched = $this->createMock(QuoteItem::class);

        (new MarkRepricedCartLines($repriced))->execute(
            $this->observer(['items' => [$parent, $child, 'not a line']])
        );

        self::assertTrue($repriced->isAwaitingTotals($parent));
        self::assertTrue($repriced->isAwaitingTotals($child));
        self::assertFalse($repriced->isAwaitingTotals($untouched));

        $collected = new MarkCartTotalsCollected($repriced);
        $collected->execute($this->observer(['quote' => $this->cart([$untouched])]));
        self::assertTrue($repriced->isAwaitingTotals($parent));

        $collected->execute($this->observer(['quote' => $this->cart([$parent, $child])]));
        self::assertFalse($repriced->isAwaitingTotals($parent));
        self::assertFalse($repriced->isAwaitingTotals($child));
    }

    /**
     * Events without lines or without a cart change nothing
     *
     * @return void
     */
    public function testEventsWithoutTheirDataChangeNothing(): void
    {
        $repriced = new RepricedLines();
        $line = $this->createMock(QuoteItem::class);
        $repriced->markRepriced($line);

        (new MarkRepricedCartLines($repriced))->execute($this->observer([]));
        (new MarkCartTotalsCollected($repriced))->execute($this->observer(['quote' => 'not a cart']));

        self::assertTrue($repriced->isAwaitingTotals($line));
    }

    /**
     * A cart holding the given lines
     *
     * @param list<QuoteItem> $lines
     * @return Quote
     */
    private function cart(array $lines): Quote
    {
        $cart = $this->createMock(Quote::class);
        $cart->method('getAllItems')->willReturn($lines);

        return $cart;
    }

    /**
     * An observer carrying the given event data
     *
     * @param array<string, mixed> $data
     * @return Observer
     */
    private function observer(array $data): Observer
    {
        return new Observer(['event' => new Event($data)]);
    }
}
