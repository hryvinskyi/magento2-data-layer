<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\ItemField;

use Hryvinskyi\DataLayer\Model\ItemField\ProductAttributeValues;
use PHPUnit\Framework\TestCase;

/**
 * Loaded values are used as they are; every other product's value is read in one go
 */
class ProductAttributeValuesTest extends TestCase
{
    use SubjectDoubles;

    /**
     * Values the products were loaded with are not read again; the others are read together, once
     *
     * @return void
     */
    public function testReadsOnlyWhatWasNotLoadedInOneCall(): void
    {
        $reader = new FakeAttributeValueReader(['mpn' => [2 => 'MPN-2', 3 => ' MPN-3 ']]);
        $values = (new ProductAttributeValues($reader))->values(
            [
                1 => $this->product(1, 'A', ['mpn' => 'MPN-1']),
                2 => $this->product(2, 'B'),
                3 => $this->product(3, 'C'),
                4 => $this->product(4, 'D', ['mpn' => null]),
            ],
            'mpn',
            5
        );

        self::assertSame([1 => 'MPN-1', 2 => 'MPN-2', 3 => 'MPN-3'], $values);
        self::assertSame([['productIds' => [2, 3], 'attributeCode' => 'mpn', 'storeId' => 5]], $reader->calls);
    }

    /**
     * Nothing is read when every product was loaded with the attribute
     *
     * @return void
     */
    public function testNothingIsReadWhenEverythingWasLoaded(): void
    {
        $reader = new FakeAttributeValueReader();
        $values = (new ProductAttributeValues($reader))->values(
            [7 => $this->product(7, 'A', ['manufacturer' => ['12', '13']])],
            'manufacturer',
            1
        );

        self::assertSame([7 => '12,13'], $values);
        self::assertSame([], $reader->calls);
    }

    /**
     * The product id is the value of `entity_id`, without any read
     *
     * @return void
     */
    public function testProductIdNeedsNoRead(): void
    {
        $reader = new FakeAttributeValueReader();

        self::assertSame(
            [42 => '42'],
            (new ProductAttributeValues($reader))->values([42 => $this->product(42)], 'entity_id', 1)
        );
        self::assertSame([], $reader->calls);
    }
}
