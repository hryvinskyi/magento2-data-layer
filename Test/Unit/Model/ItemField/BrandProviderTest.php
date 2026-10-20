<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\ItemField;

use Hryvinskyi\DataLayer\Model\ItemField\BrandProvider;
use Hryvinskyi\DataLayer\Model\ItemField\ProductAttributeValues;
use PHPUnit\Framework\TestCase;

/**
 * The brand is the label of the product's brand option, read in one call per concern for every product
 */
class BrandProviderTest extends TestCase
{
    use SubjectDoubles;

    private const BRAND = ['hryvinskyi_datalayer/item/brand_attribute' => 'manufacturer'];

    /**
     * Without a brand attribute nothing is reported and nothing is read
     *
     * @return void
     */
    public function testNoBrandAttributeReadsNothing(): void
    {
        $values = new FakeAttributeValueReader(['manufacturer' => [1 => '10']]);
        $labels = new FakeOptionLabelReader([10 => 'Acme']);
        $provider = new BrandProvider($this->settings(), new ProductAttributeValues($values), $labels);

        self::assertSame([], $provider->provide([$this->subject($this->product(1))], 'view_item'));
        self::assertSame([], $values->calls);
        self::assertSame([], $labels->calls);
    }

    /**
     * Option ids of all products are read once and their labels once; several options are joined with a comma
     *
     * @return void
     */
    public function testManySubjectsReadOnceEach(): void
    {
        $values = new FakeAttributeValueReader(['manufacturer' => [1 => '10', 2 => '11,12', 3 => '10']]);
        $labels = new FakeOptionLabelReader([10 => 'Acme', 11 => 'Globex', 12 => 'Initech']);
        $provider = new BrandProvider($this->settings(self::BRAND), new ProductAttributeValues($values), $labels);

        $fields = $provider->provide(
            [
                $this->subject($this->product(1)),
                $this->subject($this->product(2)),
                $this->subject($this->product(3)),
                $this->subject($this->product(4)),
            ],
            'view_item_list'
        );

        self::assertSame(
            [
                0 => ['item_brand' => 'Acme'],
                1 => ['item_brand' => 'Globex, Initech'],
                2 => ['item_brand' => 'Acme'],
            ],
            $fields
        );
        self::assertCount(1, $values->calls);
        self::assertSame([1, 2, 3, 4], $values->calls[0]['productIds']);
        self::assertCount(1, $labels->calls);
        self::assertSame(['10', '11', '12'], $labels->calls[0]['optionIds']);
    }

    /**
     * An option id the product was loaded with is not read again
     *
     * @return void
     */
    public function testLoadedOptionIdIsUsed(): void
    {
        $values = new FakeAttributeValueReader();
        $labels = new FakeOptionLabelReader([10 => 'Acme']);
        $provider = new BrandProvider($this->settings(self::BRAND), new ProductAttributeValues($values), $labels);

        self::assertSame(
            [0 => ['item_brand' => 'Acme']],
            $provider->provide([$this->subject($this->product(1, 'A', ['manufacturer' => '10']))], 'view_item')
        );
        self::assertSame([], $values->calls);
    }
}
