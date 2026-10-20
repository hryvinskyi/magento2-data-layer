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
use Hryvinskyi\DataLayer\Model\ItemField\LineProvider;
use Hryvinskyi\DataLayer\Test\Unit\Model\FakeScopeConfig;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Line items report their unit discount (only when there is one), the coupon and the store's name
 */
class LineProviderTest extends TestCase
{
    use SubjectDoubles;

    /**
     * Discount per unit excluding tax, coupon and affiliation for line items; nothing for a catalogue item
     *
     * @return void
     */
    public function testLineParameters(): void
    {
        $fields = $this->provider()->provide(
            [
                $this->subject($this->product(1)),
                $this->subject($this->product(2), ['cart' => $this->cartLine(
                    ['price' => 10, 'discount_amount' => 12.1, 'discount_tax_compensation_amount' => 2.1, 'qty' => 3],
                    'SPRING'
                )]),
                $this->subject($this->product(3), ['order' => $this->orderLine(['qty_ordered' => 1])]),
            ],
            'purchase'
        );

        self::assertSame(
            [
                1 => ['discount' => 3.33, 'coupon' => 'SPRING', 'affiliation' => 'The Store'],
                2 => ['affiliation' => 'The Store'],
            ],
            $fields
        );
    }

    /**
     * On the line basis including tax, with discounts applied to prices including tax, the discount is the discount as
     * applied; the catalogue basis does not change it
     *
     * @return void
     */
    public function testDiscountIncludingTax(): void
    {
        $subjects = [$this->subject($this->product(2), ['order' => $this->orderLine([
            'discount_amount' => 12.1,
            'discount_tax_compensation_amount' => 2.1,
            'qty_ordered' => 2,
        ])])];

        $lineBasis = $this->provider(['hryvinskyi_datalayer/item/line_price_includes_tax' => '1'])
            ->provide($subjects, 'purchase');
        $catalogueBasis = $this->provider(['hryvinskyi_datalayer/item/catalogue_price_includes_tax' => '1'])
            ->provide($subjects, 'purchase');

        self::assertSame(6.05, $lineBasis[0]['discount']);
        self::assertSame(5.0, $catalogueBasis[0]['discount']);
    }

    /**
     * An order line whose order cannot be resolved still reports its discount and the store, without a coupon
     *
     * @return void
     */
    public function testUnresolvedOrderKeepsTheOtherParameters(): void
    {
        $fields = $this->provider()->provide(
            [$this->subject($this->product(2), ['order' => $this->orderLine(
                ['discount_amount' => 6, 'qty_ordered' => 2],
                'IGNORED',
                false
            )])],
            'purchase'
        );

        self::assertSame([0 => ['discount' => 3.0, 'affiliation' => 'The Store']], $fields);
    }

    /**
     * A store view that does not exist has no affiliation
     *
     * @return void
     */
    public function testUnknownStoreHasNoAffiliation(): void
    {
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willThrowException(new NoSuchEntityException());

        self::assertSame(
            [],
            (new LineProvider(
                $this->settings(),
                new LineFigures(new RepricedLines(), new FakeScopeConfig()),
                $stores
            ))->provide(
                [$this->subject($this->product(2), ['cart' => $this->cartLine(['qty' => 1])])],
                'add_to_cart'
            )
        );
    }

    /**
     * A line provider reading a store named "The Store" whose discounts apply to prices including tax
     *
     * @param array<string, mixed> $settings
     * @return LineProvider
     */
    private function provider(array $settings = []): LineProvider
    {
        $store = $this->createMock(Store::class);
        $store->method('getFrontendName')->willReturn('The Store');
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return new LineProvider(
            $this->settings($settings),
            new LineFigures(new RepricedLines(), new FakeScopeConfig(['tax/calculation/discount_tax' => '1'])),
            $stores
        );
    }
}
