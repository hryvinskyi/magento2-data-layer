<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\ItemField;

use Hryvinskyi\DataLayer\Model\Cart\RepricedLines;
use Hryvinskyi\DataLayer\Model\ItemField\LineFigures;
use Hryvinskyi\DataLayer\Test\Unit\Model\FakeScopeConfig;
use PHPUnit\Framework\TestCase;

/**
 * Unit price, unit discount and coupon of cart and order lines, on either tax basis
 */
class LineFiguresTest extends TestCase
{
    use SubjectDoubles;

    /**
     * A cart line's price excluding tax is its calculation price (a custom price when set); including tax its own
     *
     * @return void
     */
    public function testCartLinePrice(): void
    {
        $figures = new LineFigures(new RepricedLines(), new FakeScopeConfig());
        $plain = $this->subject(
            $this->product(1),
            ['cart' => $this->cartLine(['price' => 10, 'price_incl_tax' => 12.1])]
        );
        $custom = $this->subject($this->product(1), ['cart' => $this->cartLine(['price' => 10, 'custom_price' => 8])]);
        $uncollected = $this->subject($this->product(1), ['cart' => $this->cartLine()]);

        self::assertSame(10.0, $figures->unitPrice($plain, false));
        self::assertSame(12.1, $figures->unitPrice($plain, true));
        self::assertSame(8.0, $figures->unitPrice($custom, false));
        self::assertNull($figures->unitPrice($uncollected, false));
        self::assertNull($figures->unitPrice($uncollected, true));
    }

    /**
     * An order line's price is the price it was ordered at, on either basis
     *
     * @return void
     */
    public function testOrderLinePrice(): void
    {
        $figures = new LineFigures(new RepricedLines(), new FakeScopeConfig());
        $subject = $this->subject(
            $this->product(1),
            ['order' => $this->orderLine(['price' => 20, 'price_incl_tax' => 24.2])]
        );

        self::assertSame(20.0, $figures->unitPrice($subject, false));
        self::assertSame(24.2, $figures->unitPrice($subject, true));
    }

    /**
     * A catalogue item has no line figures
     *
     * @return void
     */
    public function testCatalogueItemHasNone(): void
    {
        $figures = new LineFigures(new RepricedLines(), new FakeScopeConfig());
        $subject = $this->subject($this->product(1));

        self::assertNull($figures->unitPrice($subject, false));
        self::assertNull($figures->unitDiscount($subject, false));
        self::assertNull($figures->couponCode($subject));
    }

    /**
     * The discount is divided by the line's own quantity; excluding tax its tax part is taken off; with discounts
     * applied to prices including tax, the discount including tax is the line's discount as applied
     *
     * @return void
     */
    public function testUnitDiscount(): void
    {
        $figures = new LineFigures(new RepricedLines(), new FakeScopeConfig(['tax/calculation/discount_tax' => '1']));
        $subject = $this->subject(
            $this->product(1),
            ['quantity' => 1.0, 'order' => $this->orderLine([
                'discount_amount' => 24.0,
                'discount_tax_compensation_amount' => 4.0,
                'qty_ordered' => 4,
            ])]
        );
        $noQuantity = $this->subject(
            $this->product(1),
            ['cart' => $this->cartLine(['price' => 10, 'discount_amount' => 5])]
        );

        self::assertSame(5.0, $figures->unitDiscount($subject, false));
        self::assertSame(6.0, $figures->unitDiscount($subject, true));
        self::assertNull($figures->unitDiscount($noQuantity, false));
    }

    /**
     * With discounts applied to prices excluding tax, a line's discount holds no tax: including tax it is raised by
     * the line's own tax rate, for an order line and a cart line alike; excluding tax it is the discount as applied
     *
     * @return void
     */
    public function testUnitDiscountAppliedExcludingTax(): void
    {
        $settings = new FakeScopeConfig(['tax/calculation/discount_tax' => '0']);
        $figures = new LineFigures(new RepricedLines(), $settings);
        $orderLine = $this->subject(
            $this->product(1),
            ['store' => 3, 'order' => $this->orderLine([
                'discount_amount' => 20.0,
                'tax_percent' => 25.0,
                'qty_ordered' => 4,
            ])]
        );
        $cartLine = $this->subject(
            $this->product(1),
            ['store' => 3, 'cart' => $this->cartLine([
                'price' => 10,
                'price_incl_tax' => 12.5,
                'discount_amount' => 6.0,
                'tax_percent' => 25.0,
                'qty' => 2,
            ])]
        );

        self::assertSame(5.0, $figures->unitDiscount($orderLine, false));
        self::assertSame(6.25, $figures->unitDiscount($orderLine, true));
        self::assertSame(3.0, $figures->unitDiscount($cartLine, false));
        self::assertSame(3.75, $figures->unitDiscount($cartLine, true));
        self::assertContains(3, $settings->askedScopes);
    }

    /**
     * The coupon of the cart or of the order
     *
     * @return void
     */
    public function testCoupon(): void
    {
        $figures = new LineFigures(new RepricedLines(), new FakeScopeConfig());

        self::assertSame(
            'CART10',
            $figures->couponCode($this->subject($this->product(1), ['cart' => $this->cartLine([], 'CART10')]))
        );
        self::assertSame(
            'ORDER5',
            $figures->couponCode($this->subject($this->product(1), ['order' => $this->orderLine([], 'ORDER5')]))
        );
        self::assertNull($figures->couponCode($this->subject($this->product(1), ['cart' => $this->cartLine([], ' ')])));
    }

    /**
     * A cart line whose totals were not collected has no discount yet, on either basis
     *
     * @return void
     */
    public function testFreshCartLineHasNoDiscount(): void
    {
        $figures = new LineFigures(new RepricedLines(), new FakeScopeConfig());
        $fresh = $this->subject($this->product(1), ['cart' => $this->cartLine(['qty' => 2])]);

        self::assertNull($figures->unitDiscount($fresh, false));
        self::assertNull($figures->unitDiscount($fresh, true));
    }

    /**
     * An order line whose order cannot be resolved has no coupon, and its price and discount are still read
     *
     * @return void
     */
    public function testUnresolvedOrderHasNoCouponButKeepsItsFigures(): void
    {
        $figures = new LineFigures(new RepricedLines(), new FakeScopeConfig());
        $subject = $this->subject($this->product(1), ['order' => $this->orderLine(
            ['price' => 20, 'discount_amount' => 4, 'qty_ordered' => 2],
            'IGNORED',
            false
        )]);

        self::assertNull($figures->couponCode($subject));
        self::assertSame(20.0, $figures->unitPrice($subject, false));
        self::assertSame(2.0, $figures->unitDiscount($subject, false));
    }

    /**
     * A cart line without its cart has no coupon
     *
     * @return void
     */
    public function testCartLineWithoutCartHasNoCoupon(): void
    {
        self::assertNull((new LineFigures(new RepricedLines(), new FakeScopeConfig()))->couponCode(
            $this->subject($this->product(1), ['cart' => $this->cartLine(['price' => 10], 'IGNORED', false)])
        ));
    }
}
