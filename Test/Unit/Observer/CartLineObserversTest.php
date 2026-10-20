<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Observer;

use Hryvinskyi\DataLayer\Api\Cart\CartQuantityChangeReporterInterface;
use Hryvinskyi\DataLayer\Observer\ReportAddedCartLines;
use Hryvinskyi\DataLayer\Observer\ReportRemovedCartLine;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use PHPUnit\Framework\TestCase;

/**
 * Additions are reported per line without a parent, with the units the addition put on it; removals with the line's
 * whole quantity; child lines never on their own
 */
class CartLineObserversTest extends TestCase
{
    /**
     * @var list<array{string, float}>
     */
    private array $reported = [];

    /**
     * A configurable addition is reported once, for its parent line, with the requested units
     *
     * @return void
     */
    public function testConfigurableAdditionReportsTheParentOnly(): void
    {
        $parent = $this->line('parent', 5.0, 2.0);
        $child = $this->line('child', 1.0, 1.0, $parent);

        (new ReportAddedCartLines($this->reporter()))->execute($this->observer(['items' => [$parent, $child]]));

        self::assertSame([['parent', 2.0]], $this->reported);
    }

    /**
     * A grouped addition reports each of its lines with the units it put on that line, even on a line that was
     * already in the cart
     *
     * @return void
     */
    public function testGroupedAdditionReportsEveryLine(): void
    {
        $first = $this->line('first', 3.0, 3.0);
        $second = $this->line('second', 6.0, 1.0);

        (new ReportAddedCartLines($this->reporter()))->execute($this->observer(['items' => [$first, $second]]));

        self::assertSame([['first', 3.0], ['second', 1.0]], $this->reported);
    }

    /**
     * A line re-saved with a reset count reports only the change against the quantity it was loaded with: 2 → 3 adds
     * one, an unchanged re-save reports nothing, 3 → 1 removes two
     *
     * @return void
     */
    public function testRecountedLineReportsOnlyTheRealChange(): void
    {
        $observer = new ReportAddedCartLines($this->reporter());

        $observer->execute($this->observer(['items' => [$this->recounted('raised', 2.0, 3.0)]]));
        $observer->execute($this->observer(['items' => [$this->recounted('same', 3.0, 3.0)]]));
        $observer->execute($this->observer(['items' => [$this->recounted('lowered', 3.0, 1.0)]]));

        self::assertSame([['raised', 1.0], ['same', 0.0], ['lowered', -2.0]], $this->reported);
    }

    /**
     * A reset line whose loaded quantity is unknown reports nothing rather than its whole quantity; a line already in
     * the cart that was raised the ordinary way reports the units added
     *
     * @return void
     */
    public function testRecountWithoutALoadedQuantityAndAnOrdinaryRaise(): void
    {
        $observer = new ReportAddedCartLines($this->reporter());

        $observer->execute($this->observer(['items' => [$this->recounted('unknown', null, 4.0)]]));
        $observer->execute($this->observer(['items' => [$this->line('raised', 5.0, 2.0, null, 12, 3.0)]]));

        self::assertSame([['unknown', 0.0], ['raised', 2.0]], $this->reported);
    }

    /**
     * Nothing to report when the event carries no lines
     *
     * @return void
     */
    public function testAdditionWithoutLines(): void
    {
        (new ReportAddedCartLines($this->reporter()))->execute($this->observer([]));

        self::assertSame([], $this->reported);
    }

    /**
     * A removed line is reported with every unit it held; a removed child line is not reported on its own
     *
     * @return void
     */
    public function testRemoval(): void
    {
        $observer = new ReportRemovedCartLine($this->reporter());
        $parent = $this->line('parent', 4.0, 0.0);

        $observer->execute($this->observer(['quote_item' => $parent]));
        $observer->execute($this->observer(['quote_item' => $this->line('child', 1.0, 0.0, $parent)]));

        self::assertSame([['parent', -4.0]], $this->reported);
    }

    /**
     * A reporter that records each change
     *
     * @return CartQuantityChangeReporterInterface
     */
    private function reporter(): CartQuantityChangeReporterInterface
    {
        $reporter = $this->createMock(CartQuantityChangeReporterInterface::class);
        $reporter->method('report')->willReturnCallback(function (CartItemInterface $line, float $change): void {
            $this->reported[] = [(string) $line->getSku(), $change];
        });

        return $reporter;
    }

    /**
     * A cart line named by its SKU
     *
     * @param string $sku
     * @param float $quantity Its quantity after the addition
     * @param float $added The units the last addition put on it
     * @param QuoteItem|null $parent
     * @param int|null $id The line's id; null for a line the addition created
     * @param float|null $before The quantity the line recorded as its quantity before the addition
     * @param float|null $loaded The quantity the line was loaded with
     * @return QuoteItem
     */
    private function line(
        string $sku,
        float $quantity,
        float $added,
        ?QuoteItem $parent = null,
        ?int $id = null,
        ?float $before = null,
        ?float $loaded = null
    ): QuoteItem {
        $data = ['qty_to_add' => $added, 'previous_qty' => $before];
        $line = $this->createMock(QuoteItem::class);
        $line->method('getSku')->willReturn($sku);
        $line->method('getId')->willReturn($id);
        $line->method('getQty')->willReturn($quantity);
        $line->method('getParentItem')->willReturn($parent);
        $line->method('getData')->willReturnCallback(
            static fn (string $key = ''): mixed => $data[$key] ?? null
        );
        $line->method('getOrigData')->willReturnCallback(
            static fn (?string $key = null): mixed => $key === 'qty' ? $loaded : null
        );

        return $line;
    }

    /**
     * A line already in the cart that an addition re-saved with a reset count: it records its whole new quantity as
     * added and zero as its quantity before
     *
     * @param string $sku
     * @param float|null $loaded The quantity the line was loaded with; null when unknown
     * @param float $quantity Its quantity after the re-save
     * @return QuoteItem
     */
    private function recounted(string $sku, ?float $loaded, float $quantity): QuoteItem
    {
        return $this->line($sku, $quantity, $quantity, null, 7, 0.0, $loaded);
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
