<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Plugin\Checkout;

use Hryvinskyi\DataLayer\Api\Cart\CartQuantityChangeReporterInterface;
use Hryvinskyi\DataLayer\Model\Cart\RepricedLines;
use Hryvinskyi\DataLayer\Plugin\Checkout\CartQuantityChangePlugin;
use Hryvinskyi\DataLayer\Test\Unit\Model\RecordingLogger;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use PHPUnit\Framework\TestCase;

/**
 * A cart update reports each named line's quantity change (up, down), skips unchanged lines and lines it removed,
 * marks every changed line as awaiting totals before reporting it, returns the update's result and lets the update's
 * exception through after reporting what it applied
 */
class CartQuantityChangePluginTest extends TestCase
{
    /**
     * @var list<array{int, float}>
     */
    private array $reported = [];

    /**
     * @var array<int, QuoteItem>
     */
    private array $lines = [];

    /**
     * @var array<int, float>
     */
    private array $quantities = [];

    /**
     * @var array<int, bool>
     */
    private array $deleted = [];

    /**
     * @var RepricedLines
     */
    private RepricedLines $repricedLines;

    /**
     * @var list<array{int, bool}>
     */
    private array $awaitingWhenReported = [];

    /**
     * Raised and lowered lines report their difference; an unchanged line and a removed line report nothing here
     *
     * @return void
     */
    public function testDeltas(): void
    {
        $cart = $this->cartWith([1 => 2.0, 2 => 5.0, 3 => 4.0, 4 => 1.0]);
        $update = [1 => ['qty' => 5], 2 => ['qty' => 3], 3 => ['qty' => 4], 4 => ['qty' => 0], 99 => ['qty' => 1]];

        $result = $this->plugin()->aroundUpdateItems($cart, function (array $data) use ($cart): Cart {
            $this->quantities[1] = 5.0;
            $this->quantities[2] = 3.0;
            $this->deleted[4] = true;

            return $cart;
        }, $update);

        self::assertSame($cart, $result);
        self::assertSame([[1, 3.0], [2, -2.0]], $this->reported);
    }

    /**
     * A changed line is already awaiting totals when its change is reported, so its old price and discount are not
     * read for its new quantity; an unchanged line keeps its calculated figures
     *
     * @return void
     */
    public function testChangedLinesAwaitTotalsWhenReported(): void
    {
        $cart = $this->cartWith([1 => 3.0, 2 => 2.0]);

        $this->plugin()->aroundUpdateItems($cart, function () use ($cart): Cart {
            $this->quantities[1] = 1.0;

            return $cart;
        }, [1 => ['qty' => 1], 2 => ['qty' => 2]]);

        self::assertSame([[1, true]], $this->awaitingWhenReported);
        self::assertTrue($this->repricedLines->isAwaitingTotals($this->lines[1]));
        self::assertFalse($this->repricedLines->isAwaitingTotals($this->lines[2]));
    }

    /**
     * The update's exception reaches the caller, and the quantities it applied before throwing are reported
     *
     * @return void
     */
    public function testUpdateExceptionIsRethrownAfterReporting(): void
    {
        $cart = $this->cartWith([1 => 1.0]);

        try {
            $this->plugin()->aroundUpdateItems($cart, function (): never {
                $this->quantities[1] = 4.0;
                throw new LocalizedException(__('Not enough stock'));
            }, [1 => ['qty' => 4]]);
            self::fail('The update\'s exception was swallowed.');
        } catch (LocalizedException $exception) {
            self::assertSame('Not enough stock', $exception->getMessage());
        }

        self::assertSame([[1, 3.0]], $this->reported);
    }

    /**
     * An update that is not keyed by line reports nothing and still runs
     *
     * @return void
     */
    public function testUnreadableUpdateStillRuns(): void
    {
        $cart = $this->cartWith([]);
        $ran = false;

        $this->plugin()->aroundUpdateItems($cart, static function () use (&$ran, $cart): Cart {
            $ran = true;

            return $cart;
        }, 'nonsense');

        self::assertTrue($ran);
        self::assertSame([], $this->reported);
    }

    /**
     * The plugin with a recording reporter
     *
     * @return CartQuantityChangePlugin
     */
    private function plugin(): CartQuantityChangePlugin
    {
        $reporter = $this->createMock(CartQuantityChangeReporterInterface::class);
        $reporter->method('report')->willReturnCallback(function (CartItemInterface $line, float $change): void {
            $this->reported[] = [(int) $line->getItemId(), $change];
            $this->awaitingWhenReported[] = [(int) $line->getItemId(), $this->repricedLines->isAwaitingTotals($line)];
        });
        $this->repricedLines = new RepricedLines();

        return new CartQuantityChangePlugin($reporter, $this->repricedLines, new RecordingLogger());
    }

    /**
     * A cart whose lines have the given quantities, which the test changes as the update would
     *
     * @param array<int, float> $quantities Quantity by line id
     * @return Cart
     */
    private function cartWith(array $quantities): Cart
    {
        $this->quantities = $quantities;
        foreach (array_keys($quantities) as $id) {
            $line = $this->createMock(QuoteItem::class);
            $line->method('getItemId')->willReturn($id);
            $line->method('getQty')->willReturnCallback(fn (): float => $this->quantities[$id]);
            $line->method('isDeleted')->willReturnCallback(fn (): bool => $this->deleted[$id] ?? false);
            $this->lines[$id] = $line;
        }
        $quote = $this->createMock(Quote::class);
        $quote->method('getItemById')->willReturnCallback(
            fn (mixed $id): QuoteItem|false => is_int($id) ? ($this->lines[$id] ?? false) : false
        );
        $cart = $this->createMock(Cart::class);
        $cart->method('getQuote')->willReturn($quote);

        return $cart;
    }
}
