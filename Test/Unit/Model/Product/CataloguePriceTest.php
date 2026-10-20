<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Product;

use Hryvinskyi\DataLayer\Api\Product\CataloguePriceStrategyInterface;
use Hryvinskyi\DataLayer\Api\Product\QuantityPriceInfoInterface;
use Hryvinskyi\DataLayer\Api\Product\TaxBasisPriceInterface;
use Hryvinskyi\DataLayer\Model\Product\CataloguePriceResolver;
use Hryvinskyi\DataLayer\Model\Product\Price\FinalPriceAmount;
use Hryvinskyi\DataLayer\Model\Product\Price\LowestAssociatedAmount;
use Hryvinskyi\DataLayer\Model\Product\Price\MinimalFinalAmount;
use Hryvinskyi\DataLayer\Model\Product\Price\QuantityPriceInfo;
use Hryvinskyi\DataLayer\Model\Product\Price\TaxBasisPrice;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPriceInterface;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Amount\Base;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfo\Factory as PriceInfoFactory;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Tax\Api\TaxCalculationInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The catalogue price comes from the strategy of the product's type (or the default one), for the quantity asked,
 * and reads the shown amount before its adjustments; including tax, it adds the tax the catalogue computed or, when the
 * store shows prices excluding tax and the catalogue computed none, the default rate of the priced product's tax class
 */
class CataloguePriceTest extends TestCase
{
    /**
     * The tax rates asked for: product tax class and store view
     *
     * @var list<array{mixed, mixed}>
     */
    private array $rated = [];

    /**
     * An amount whose value includes tax reads as its amount before adjustments, unless tax is wanted
     *
     * @return void
     */
    public function testAmountBeforeAdjustmentsAndTaxOnRequest(): void
    {
        $basis = $this->taxBasis(25.0);
        $amount = new Base(121.0, ['tax' => 21.0]);
        $product = $this->taxed(2, 1);

        self::assertSame(100.0, $basis->price($amount, $product, false));
        self::assertSame(121.0, $basis->price($amount, $product, true));
        self::assertSame([], $this->rated, 'the catalogue computed the tax: nothing is calculated again');
    }

    /**
     * Including tax adds only the tax adjustment, never another one
     *
     * @return void
     */
    public function testOnlyTheTaxAdjustmentIsAdded(): void
    {
        $basis = $this->taxBasis(25.0);
        $amount = new Base(130.0, ['tax' => 21.0, 'weee' => 9.0]);

        self::assertSame(121.0, $basis->price($amount, $this->taxed(2, 1), true));
        self::assertSame(100.0, $basis->price($amount, $this->taxed(2, 1), false));
    }

    /**
     * A store that shows prices excluding tax computes no tax adjustment: including tax, the price is raised by the
     * default rate of the priced product's tax class in its store view
     *
     * @return void
     */
    public function testTaxIsCalculatedWhenTheCatalogueShowsNone(): void
    {
        $basis = $this->taxBasis(25.0);
        $product = $this->taxed(2, 3);

        self::assertSame(125.0, $basis->price(new Base(100.0), $product, true));
        self::assertSame(100.0, $basis->price(new Base(100.0), $product, false));
        self::assertSame([[2, '3']], $this->rated);
    }

    /**
     * A product without a tax class pays no tax and no rate is asked for
     *
     * @return void
     */
    public function testNoTaxClassReportsTheAmount(): void
    {
        $basis = $this->taxBasis(25.0);

        self::assertSame(80.0, $basis->price(new Base(80.0), $this->taxed(null, 1), true));
        self::assertSame(80.0, $basis->price(new Base(80.0), $this->taxed(0, 1), true));
        self::assertSame([], $this->rated);
    }

    /**
     * A type's own strategy is used, with the basis and quantity asked; any other type falls back to the default one
     *
     * @return void
     */
    public function testTypeStrategyAndDefaultFallback(): void
    {
        $asked = [];
        $resolver = new CataloguePriceResolver([
            'default' => $this->fixed(10.0, $asked),
            'bundle' => $this->fixed(50.0, $asked),
            'grouped' => $this->fixed(null, $asked),
        ]);

        self::assertSame(50.0, $resolver->unitPrice($this->product('bundle'), false));
        self::assertSame(10.0, $resolver->unitPrice($this->product('virtual'), true, 3.0));
        self::assertNull($resolver->unitPrice($this->product('grouped'), false));
        self::assertSame([[false, 1.0], [true, 3.0], [false, 1.0]], $asked);
    }

    /**
     * Without a default strategy, or with a foreign one, the resolver is refused
     *
     * @dataProvider invalidStrategies
     * @param array<mixed> $strategies
     * @return void
     */
    #[DataProvider('invalidStrategies')]
    public function testInvalidStrategiesAreRefused(array $strategies): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CataloguePriceResolver($strategies);
    }

    /**
     * Strategy sets the resolver refuses
     *
     * @return array<string, array{array<mixed>}>
     */
    public static function invalidStrategies(): array
    {
        return [
            'no default' => [['bundle' => new \stdClass()]],
            'foreign' => [['default' => new \stdClass()]],
        ];
    }

    /**
     * The default strategy is the product's final price for the quantity, priced on the product itself
     *
     * @return void
     */
    public function testFinalPriceAmount(): void
    {
        $product = $this->createMock(Product::class);
        $quantities = [];
        $strategy = new FinalPriceAmount(
            $this->pricing([3 => $this->finalPrice(new Base(15.0))], $quantities),
            $this->recordingBasis($priced)
        );

        self::assertSame(15.0, $strategy->price($product, false, 3.0));
        self::assertSame([3.0], $quantities);
        self::assertSame($product, $priced);
        self::assertNull($strategy->price($this->product('simple'), false, 1.0), 'a product that is not saleable');
    }

    /**
     * The bundle strategy is the final price's minimal amount
     *
     * @return void
     */
    public function testMinimalFinalAmount(): void
    {
        $finalPrice = $this->createMockForIntersectionOfInterfaces([PriceInterface::class, FinalPriceInterface::class]);
        $finalPrice->method('getMinimalPrice')->willReturn(new Base(30.0));
        $finalPrice->method('getAmount')->willReturn(new Base(99.0));
        $quantities = [];
        $strategy = new MinimalFinalAmount(
            $this->pricing([1 => $finalPrice], $quantities),
            $this->recordingBasis($priced)
        );

        self::assertSame(30.0, $strategy->price($this->createMock(Product::class), false, 1.0));
    }

    /**
     * The grouped strategy is the cheapest associated product's own final price, priced (and taxed) on that product
     *
     * @return void
     */
    public function testLowestAssociatedAmount(): void
    {
        $expensive = $this->createMock(Product::class);
        $cheap = $this->createMock(Product::class);
        $amounts = [
            spl_object_id($expensive) => $this->finalPrice(new Base(24.2, ['tax' => 4.2])),
            spl_object_id($cheap) => $this->finalPrice(new Base(12.1, ['tax' => 2.1])),
        ];
        $pricing = $this->createMock(QuantityPriceInfoInterface::class);
        $pricing->method('forQuantity')->willReturnCallback(
            fn (SaleableInterface $product): PriceInfoInterface => $this->priceInfo($amounts[spl_object_id($product)])
        );
        $type = $this->createMock(Grouped::class);
        $type->method('getAssociatedProducts')->willReturn([$expensive, $cheap, 'not a product']);
        $grouped = $this->createMock(Product::class);
        $grouped->method('getTypeInstance')->willReturn($type);

        $price = (new LowestAssociatedAmount($pricing, $this->recordingBasis($priced)))->price($grouped, true, 1.0);

        self::assertSame(10.0, $price, 'the recording basis reads the amount before adjustments');
        self::assertSame($cheap, $priced);
    }

    /**
     * A grouped product without associated products has no price
     *
     * @return void
     */
    public function testGroupedWithoutAssociatedProductsHasNoPrice(): void
    {
        $type = $this->createMock(Grouped::class);
        $type->method('getAssociatedProducts')->willReturn([]);
        $grouped = $this->createMock(Product::class);
        $grouped->method('getTypeInstance')->willReturn($type);

        self::assertNull(
            (new LowestAssociatedAmount(
                $this->createMock(QuantityPriceInfoInterface::class),
                $this->taxBasis(0.0)
            ))->price($grouped, false, 1.0)
        );
    }

    /**
     * One unit uses the product's own pricing; another quantity gets pricing of its own, built for a copy of the
     * product that carries that quantity, so the product itself keeps its own quantity
     *
     * @return void
     */
    public function testQuantityPricing(): void
    {
        $own = $this->createMock(PriceInfoInterface::class);
        $forThree = $this->createMock(PriceInfoInterface::class);
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPriceInfo'])
            ->getMock();
        $product->method('getPriceInfo')->willReturn($own);
        $product->setData('qty', 2.0);
        $priced = null;
        $factory = $this->createMock(PriceInfoFactory::class);
        $factory->expects(self::once())->method('create')->willReturnCallback(
            static function (SaleableInterface $item, array $arguments) use (&$priced, $forThree): PriceInfoInterface {
                $priced = [$item, $item->getQty(), $arguments];

                return $forThree;
            }
        );
        $pricing = new QuantityPriceInfo($factory);

        self::assertSame($own, $pricing->forQuantity($product, 1.0));
        self::assertSame($own, $pricing->forQuantity($product, 0.0));
        self::assertSame($forThree, $pricing->forQuantity($product, 3.0));
        self::assertIsArray($priced);
        self::assertNotSame($product, $priced[0]);
        self::assertSame(3.0, $priced[1]);
        self::assertSame(['quantity' => 3.0], $priced[2]);
        self::assertSame(2.0, $product->getData('qty'));
    }

    /**
     * A strategy returning a fixed price, recording the basis and quantity it was asked for
     *
     * @param float|null $price
     * @param list<array{bool, float}> $asked
     * @return CataloguePriceStrategyInterface
     */
    private function fixed(?float $price, array &$asked): CataloguePriceStrategyInterface
    {
        $strategy = $this->createMock(CataloguePriceStrategyInterface::class);
        $strategy->method('price')->willReturnCallback(
            static function (ProductInterface $product, bool $includingTax, float $quantity) use ($price, &$asked) {
                $asked[] = [$includingTax, $quantity];

                return $price;
            }
        );

        return $strategy;
    }

    /**
     * A product of the given type
     *
     * @param string $typeId
     * @return ProductInterface
     */
    private function product(string $typeId): ProductInterface
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getTypeId')->willReturn($typeId);

        return $product;
    }

    /**
     * A product of the given tax class in the given store view
     *
     * @param int|null $taxClassId
     * @param int $storeId
     * @return Product
     */
    private function taxed(?int $taxClassId, int $storeId): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getData')->willReturnCallback(
            static fn (string $key = ''): mixed => ['tax_class_id' => $taxClassId, 'store_id' => $storeId][$key] ?? null
        );

        return $product;
    }

    /**
     * The tax basis over a tax calculation that knows one rate for every class, recording what it is asked
     *
     * @param float $rate The rate, in percent
     * @return TaxBasisPrice
     */
    private function taxBasis(float $rate): TaxBasisPrice
    {
        $calculation = $this->createMock(TaxCalculationInterface::class);
        $calculation->method('getCalculatedRate')->willReturnCallback(
            function (mixed $taxClassId, mixed $customerId, mixed $storeId) use ($rate): float {
                $this->rated[] = [$taxClassId, $storeId];

                return $rate;
            }
        );

        return new TaxBasisPrice($calculation);
    }

    /**
     * Quantity pricing that answers with the given final price per quantity, recording the quantities asked
     *
     * @param array<int, PriceInterface> $finalPrices Final price by whole quantity
     * @param list<float> $quantities
     * @return QuantityPriceInfoInterface
     */
    private function pricing(array $finalPrices, array &$quantities): QuantityPriceInfoInterface
    {
        $pricing = $this->createMock(QuantityPriceInfoInterface::class);
        $pricing->method('forQuantity')->willReturnCallback(
            function (
                SaleableInterface $product,
                float $quantity
            ) use (
                $finalPrices,
                &$quantities
            ): PriceInfoInterface {
                $quantities[] = $quantity;

                return $this->priceInfo($finalPrices[(int) $quantity]);
            }
        );

        return $pricing;
    }

    /**
     * Pricing whose final price is the given price
     *
     * @param PriceInterface $finalPrice
     * @return PriceInfoInterface
     */
    private function priceInfo(PriceInterface $finalPrice): PriceInfoInterface
    {
        $priceInfo = $this->createMock(PriceInfoInterface::class);
        $priceInfo->method('getPrice')->with('final_price')->willReturn($finalPrice);

        return $priceInfo;
    }

    /**
     * A final price whose amount is the given amount
     *
     * @param AmountInterface $amount
     * @return PriceInterface
     */
    private function finalPrice(AmountInterface $amount): PriceInterface
    {
        $finalPrice = $this->createMock(PriceInterface::class);
        $finalPrice->method('getAmount')->willReturn($amount);

        return $finalPrice;
    }

    /**
     * A tax basis that reads the amount before adjustments and records the product the price was taken on
     *
     * @param SaleableInterface|null $priced Set to the priced product on each call
     * @param-out SaleableInterface|null $priced
     * @return TaxBasisPriceInterface
     */
    private function recordingBasis(?SaleableInterface &$priced): TaxBasisPriceInterface
    {
        $priced = null;
        $basis = $this->createMock(TaxBasisPriceInterface::class);
        $basis->method('price')->willReturnCallback(
            static function (AmountInterface $amount, SaleableInterface $item) use (&$priced): float {
                $priced = $item;

                return (float) $amount->getBaseAmount();
            }
        );

        return $basis;
    }
}
