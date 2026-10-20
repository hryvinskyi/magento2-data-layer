<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\ItemField;

use Hryvinskyi\DataLayer\Api\ItemField\LineFiguresInterface;
use Hryvinskyi\DataLayer\Api\Product\CataloguePriceResolverInterface;
use Hryvinskyi\DataLayer\Api\Product\LineVariantResolverInterface;
use Hryvinskyi\DataLayer\Model\Cart\RepricedLines;
use Hryvinskyi\DataLayer\Model\ItemField\LineFigures;
use Hryvinskyi\DataLayer\Model\ItemField\PriceProvider;
use Hryvinskyi\DataLayer\Model\ItemField\VariantProvider;
use Hryvinskyi\DataLayer\Test\Unit\Model\FakeScopeConfig;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderItemInterface;
use PHPUnit\Framework\TestCase;

/**
 * A line item reports its line's price and variant; a catalogue item its listing price and no variant
 */
class PriceAndVariantProviderTest extends TestCase
{
    use SubjectDoubles;

    /**
     * Line prices come from the line on the line basis, catalogue prices from the resolver on the catalogue basis,
     * rounded; a cart line awaiting totals asks the catalogue on the line basis
     *
     * @return void
     */
    public function testPriceSourcesAndBasis(): void
    {
        $asked = [];
        $lines = $this->createMock(LineFiguresInterface::class);
        $lines->method('unitPrice')->willReturnCallback(
            function (ItemSubjectInterface $subject, bool $includingTax) use (&$asked): ?float {
                $asked[] = ['line', $includingTax];

                return $subject->getOrderItem() !== null ? 12.345 : null;
            }
        );
        $catalogue = $this->createMock(CataloguePriceResolverInterface::class);
        $catalogue->method('unitPrice')->willReturnCallback(
            function (ProductInterface $product, bool $includingTax) use (&$asked): ?float {
                $asked[] = ['catalogue', $includingTax];

                return $product->getId() === 1 ? 99.999 : null;
            }
        );

        $fields = (new PriceProvider(
            $this->settings(['hryvinskyi_datalayer/item/catalogue_price_includes_tax' => '1']),
            $lines,
            $catalogue
        ))->provide(
            [
                $this->subject($this->product(1)),
                $this->subject($this->product(2)),
                $this->subject($this->product(3), ['order' => $this->orderLine()]),
                $this->subject($this->product(4), ['cart' => $this->cartLine()]),
            ],
            'purchase'
        );

        self::assertSame([0 => ['price' => 100.0], 2 => ['price' => 12.35]], $fields);
        self::assertSame(
            [['catalogue', true], ['catalogue', true], ['line', false], ['line', false], ['catalogue', false]],
            $asked
        );
    }

    /**
     * Prices are excluding tax unless the store says otherwise
     *
     * @return void
     */
    public function testExcludingTaxByDefault(): void
    {
        $catalogue = $this->createMock(CataloguePriceResolverInterface::class);
        $catalogue->expects(self::once())->method('unitPrice')->with(self::anything(), false)->willReturn(10.0);

        (new PriceProvider($this->settings(), $this->createMock(LineFiguresInterface::class), $catalogue))
            ->provide([$this->subject($this->product(1))], 'view_item');
    }

    /**
     * Each basis is read for its own kind of item: a line basis including tax leaves catalogue items excluding tax,
     * and a catalogue basis including tax leaves line items excluding tax
     *
     * @return void
     */
    public function testEachBasisAppliesToItsOwnItems(): void
    {
        $lineBases = [];
        $lines = $this->createMock(LineFiguresInterface::class);
        $lines->method('unitPrice')->willReturnCallback(
            static function (ItemSubjectInterface $subject, bool $includingTax) use (&$lineBases): float {
                $lineBases[] = $includingTax;

                return 1.0;
            }
        );
        $asked = [];
        $catalogue = $this->catalogue([1 => 2.0], $asked);
        $subjects = [
            $this->subject($this->product(1)),
            $this->subject($this->product(2), ['order' => $this->orderLine()]),
        ];

        (new PriceProvider(
            $this->settings(['hryvinskyi_datalayer/item/line_price_includes_tax' => '1']),
            $lines,
            $catalogue
        ))->provide($subjects, 'purchase');
        (new PriceProvider(
            $this->settings(['hryvinskyi_datalayer/item/catalogue_price_includes_tax' => '1']),
            $lines,
            $catalogue
        ))->provide($subjects, 'purchase');

        self::assertSame([[1, false, 1.0], [1, true, 1.0]], $asked);
        self::assertSame([true, false], $lineBases);
    }

    /**
     * A cart line just added (no totals yet) reports the catalogue price of its product, on the line basis
     *
     * @return void
     */
    public function testFreshCartLineReportsTheCataloguePrice(): void
    {
        $asked = [];
        $provider = $this->withRealLines($this->catalogue([1 => 49.999], $asked), true);

        self::assertSame(
            [0 => ['price' => 50.0]],
            $provider->provide(
                [$this->subject($this->product(1), ['cart' => $this->cartLine(['qty' => 1, 'price' => 40])])],
                'add_to_cart'
            )
        );
        self::assertSame([[1, true, 1.0]], $asked);
    }

    /**
     * A cart line with collected totals reports its calculation price; the catalogue is not asked
     *
     * @return void
     */
    public function testCalculatedCartLineReportsItsOwnPrice(): void
    {
        $asked = [];
        $provider = $this->withRealLines($this->catalogue([1 => 49.0], $asked), false);

        self::assertSame(
            [0 => ['price' => 8.0]],
            $provider->provide(
                [$this->subject(
                    $this->product(1),
                    ['cart' => $this->cartLine(['qty' => 1, 'price' => 10, 'custom_price' => 8])]
                )],
                'view_cart'
            )
        );
        self::assertSame([], $asked);
    }

    /**
     * A configurable line just added reports the catalogue price of its chosen child, not of its parent
     *
     * @return void
     */
    public function testFreshConfigurableLineReportsTheChildPrice(): void
    {
        $asked = [];
        $provider = $this->withRealLines($this->catalogue([1 => 30.0, 7 => 35.0], $asked), false);

        self::assertSame(
            [0 => ['price' => 35.0]],
            $provider->provide(
                [$this->subject(
                    $this->product(1),
                    ['child' => $this->product(7), 'cart' => $this->cartLine(['qty' => 1])]
                )],
                'add_to_cart'
            )
        );
        self::assertSame([[7, false, 1.0]], $asked);
    }

    /**
     * A line an addition re-priced reports the catalogue price for the line's whole quantity even though it carries a
     * price, until its cart's totals run; then it reports its own price
     *
     * @return void
     */
    public function testRepricedLineWaitsForTheCartTotals(): void
    {
        $asked = [];
        $repriced = new RepricedLines();
        $provider = $this->withRealLines($this->catalogue([1 => 36.0], $asked), false, $repriced);
        $line = $this->cartLine(['qty' => 3, 'price' => 40, 'price_incl_tax' => 50]);
        $repriced->markRepriced($line);
        $subjects = [$this->subject($this->product(1), ['cart' => $line, 'quantity' => 1.0])];

        self::assertSame([0 => ['price' => 36.0]], $provider->provide($subjects, 'add_to_cart'));
        self::assertSame([[1, false, 3.0]], $asked);

        $cart = $this->createMock(Quote::class);
        $cart->method('getAllItems')->willReturn([$line]);
        $repriced->markTotalsCollected($cart);

        $notAsked = $this->createMock(CataloguePriceResolverInterface::class);
        $notAsked->expects(self::never())->method('unitPrice');
        self::assertSame(
            [0 => ['price' => 40.0]],
            $this->withRealLines($notAsked, false, $repriced)->provide($subjects, 'view_cart')
        );
    }

    /**
     * Only line items have a variant
     *
     * @return void
     */
    public function testVariantForLinesOnly(): void
    {
        $variants = $this->createMock(LineVariantResolverInterface::class);
        $variants->method('variant')->willReturnCallback(
            static fn (CartItemInterface|OrderItemInterface $line): ?string => $line instanceof OrderItemInterface
                ? 'Colour: Red'
                : null
        );

        self::assertSame(
            [1 => ['item_variant' => 'Colour: Red']],
            (new VariantProvider($variants))->provide(
                [
                    $this->subject($this->product(1)),
                    $this->subject($this->product(2), ['order' => $this->orderLine()]),
                    $this->subject($this->product(3), ['cart' => $this->cartLine()]),
                ],
                'purchase'
            )
        );
    }

    /**
     * A price provider reading real line figures and the given catalogue prices
     *
     * @param CataloguePriceResolverInterface $catalogue
     * @param bool $includingTax The line basis; the catalogue basis is set to the opposite, so a test proves which
     *     one a line item reads
     * @param RepricedLines|null $repriced The lines an addition re-priced; none by default
     * @return PriceProvider
     */
    private function withRealLines(
        CataloguePriceResolverInterface $catalogue,
        bool $includingTax,
        ?RepricedLines $repriced = null
    ): PriceProvider {
        return new PriceProvider(
            $this->settings([
                'hryvinskyi_datalayer/item/line_price_includes_tax' => $includingTax ? '1' : '0',
                'hryvinskyi_datalayer/item/catalogue_price_includes_tax' => $includingTax ? '0' : '1',
            ]),
            new LineFigures($repriced ?? new RepricedLines(), new FakeScopeConfig()),
            $catalogue
        );
    }

    /**
     * Catalogue prices by product id, recording the product, basis and quantity each request asked for
     *
     * @param array<int, float> $prices
     * @param list<array{int, bool, float}> $asked
     * @return CataloguePriceResolverInterface
     */
    private function catalogue(array $prices, array &$asked): CataloguePriceResolverInterface
    {
        $catalogue = $this->createMock(CataloguePriceResolverInterface::class);
        $catalogue->method('unitPrice')->willReturnCallback(
            static function (
                ProductInterface $product,
                bool $includingTax,
                float $quantity = 1.0
            ) use (
                $prices,
                &$asked
            ): ?float {
                $asked[] = [(int) $product->getId(), $includingTax, $quantity];

                return $prices[(int) $product->getId()] ?? null;
            }
        );

        return $catalogue;
    }
}
