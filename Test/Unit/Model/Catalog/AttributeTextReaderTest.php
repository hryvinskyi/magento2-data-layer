<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Catalog;

use Hryvinskyi\DataLayer\Model\Catalog\AttributeTextReader;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Attribute text is read by the reader of the attribute's input type, or by the default reader
 */
class AttributeTextReaderTest extends TestCase
{
    private const VALUES = [1 => '10', 2 => '11'];

    /**
     * An input type with a reader of its own is read by that reader, with the values and store view as given
     *
     * @return void
     */
    public function testInputTypeWithAReaderUsesIt(): void
    {
        $options = new FakeAttributeTextReader('options');
        $default = new FakeAttributeTextReader('default');
        $reader = new AttributeTextReader($this->eavConfig('select'), $default, ['select' => $options]);

        self::assertSame([1 => 'options:10', 2 => 'options:11'], $reader->texts('manufacturer', self::VALUES, 3));
        self::assertSame(
            [['attributeCode' => 'manufacturer', 'values' => self::VALUES, 'storeId' => 3]],
            $options->calls
        );
        self::assertSame([], $default->calls);
    }

    /**
     * An input type without a reader of its own is read by the default reader
     *
     * @return void
     */
    public function testOtherInputTypeUsesTheDefaultReader(): void
    {
        $options = new FakeAttributeTextReader('options');
        $reader = new AttributeTextReader(
            $this->eavConfig('text'),
            new FakeAttributeTextReader('default'),
            ['select' => $options]
        );

        self::assertSame([1 => 'default:10', 2 => 'default:11'], $reader->texts('saf_brand', self::VALUES, 1));
        self::assertSame([], $options->calls);
    }

    /**
     * An attribute that does not exist yields no text and reaches no reader
     *
     * @dataProvider missingAttributeProvider
     * @param bool $throws Whether the attribute lookup fails instead of returning an attribute without an id
     * @return void
     */
    #[DataProvider('missingAttributeProvider')]
    public function testMissingAttributeYieldsNoText(bool $throws): void
    {
        $eavConfig = $this->createMock(EavConfig::class);
        if ($throws) {
            $eavConfig->method('getAttribute')->willThrowException(new LocalizedException(__('No attribute.')));
        } else {
            $eavConfig->method('getAttribute')->willReturn($this->attribute(0, 'text'));
        }
        $default = new FakeAttributeTextReader('default');

        self::assertSame([], (new AttributeTextReader($eavConfig, $default))->texts('gone', self::VALUES, 1));
        self::assertSame([], $default->calls);
    }

    /**
     * Ways an attribute can be missing.
     *
     * @return array<string, array{bool}>
     */
    public static function missingAttributeProvider(): array
    {
        return [
            'lookup fails' => [true],
            'attribute without an id' => [false],
        ];
    }

    /**
     * No values need no attribute lookup
     *
     * @return void
     */
    public function testNoValuesReadNothing(): void
    {
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->expects(self::never())->method('getAttribute');
        $default = new FakeAttributeTextReader('default');

        self::assertSame([], (new AttributeTextReader($eavConfig, $default))->texts('manufacturer', [], 1));
        self::assertSame([], $default->calls);
    }

    /**
     * EAV config holding one product attribute with the given input type
     *
     * @param string $input Frontend input of the attribute
     * @return EavConfig
     */
    private function eavConfig(string $input): EavConfig
    {
        $eavConfig = $this->createMock(EavConfig::class);
        $eavConfig->method('getAttribute')->with('catalog_product')->willReturn($this->attribute(42, $input));

        return $eavConfig;
    }

    /**
     * An attribute with the given id and input type
     *
     * @param int $id
     * @param string $input
     * @return AbstractAttribute
     */
    private function attribute(int $id, string $input): AbstractAttribute
    {
        $attribute = $this->createMock(AbstractAttribute::class);
        $attribute->method('getAttributeId')->willReturn($id);
        $attribute->method('getFrontendInput')->willReturn($input);

        return $attribute;
    }
}
