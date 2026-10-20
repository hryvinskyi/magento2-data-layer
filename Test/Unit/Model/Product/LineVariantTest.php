<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Product;

use Hryvinskyi\DataLayer\Api\Product\LineVariantStrategyInterface;
use Hryvinskyi\DataLayer\Model\Product\LineVariantResolver;
use Hryvinskyi\DataLayer\Model\Product\Variant\BundleVariant;
use Hryvinskyi\DataLayer\Model\Product\Variant\ChosenOptions;
use Hryvinskyi\DataLayer\Model\Product\Variant\OptionsVariant;
use Magento\Catalog\Helper\Product\Configuration\ConfigurationInterface;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote\Item\Option;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order\Item as OrderItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * A line's variant is its chosen options as "Label: Value, Label: Value", read the way its product type stores them
 */
class LineVariantTest extends TestCase
{
    /**
     * @var list<string> The product types the configuration pool was asked for
     */
    private array $askedTypes = [];

    /**
     * The line's type strategy is used; any other type falls back to the default one
     *
     * @return void
     */
    public function testResolverFallsBackToDefault(): void
    {
        $resolver = new LineVariantResolver([
            'default' => $this->fixed('default'),
            'configurable' => $this->fixed('configurable'),
        ]);

        self::assertSame('configurable', $resolver->variant($this->orderLine('configurable', [])));
        self::assertSame('default', $resolver->variant($this->orderLine('downloadable', [])));
    }

    /**
     * Without a default strategy the resolver is refused
     *
     * @return void
     */
    public function testResolverNeedsDefault(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new LineVariantResolver(['bundle' => $this->fixed('x')]);
    }

    /**
     * An order line lists the configured groups in order: chosen attributes before custom options
     *
     * @return void
     */
    public function testConfigurableOrderLine(): void
    {
        $variant = new OptionsVariant(
            new ChosenOptions($this->pool([])),
            ['attributes_info', 'options', 'additional_options']
        );
        $line = $this->orderLine('configurable', [
            'options' => [['label' => 'Engraving', 'value' => 'Hi &amp; bye', 'print_value' => 'Hi & bye']],
            'attributes_info' => [['label' => 'Colour', 'value' => 'Red'], ['label' => 'Size', 'value' => 'M']],
            'additional_options' => [['label' => 'Gift', 'value' => '<b>Yes</b>']],
        ]);

        self::assertSame('Colour: Red, Size: M, Engraving: Hi & bye, Gift: Yes', $variant->variant($line));
    }

    /**
     * An order line of a type without attributes lists only its custom options; with none it has no variant
     *
     * @return void
     */
    public function testDefaultOrderLine(): void
    {
        $variant = new OptionsVariant(new ChosenOptions($this->pool([])));

        self::assertSame(
            'Warranty: 3 years',
            $variant->variant($this->orderLine('simple', [
                'attributes_info' => [['label' => 'Colour', 'value' => 'Red']],
                'options' => [['label' => 'Warranty', 'value' => '3 years']],
            ]))
        );
        self::assertNull($variant->variant($this->orderLine('simple', [])));
    }

    /**
     * A cart line's options are those of the product configuration registered for its type
     *
     * @return void
     */
    public function testCartLineReadsTheProductConfiguration(): void
    {
        $variant = new OptionsVariant(new ChosenOptions($this->pool([
            ['label' => 'Colour', 'value' => 'Blue'],
            ['label' => 'Engraving', 'value' => ['Line 1', 'Line 2']],
            ['label' => 'Empty', 'value' => ''],
        ])));

        self::assertSame(
            'Colour: Blue, Engraving: Line 1, Line 2',
            $variant->variant($this->cartLine('configurable'))
        );
        self::assertSame(['configurable'], $this->askedTypes);
    }

    /**
     * A bundle order line lists each selection under its option's title, then its custom options
     *
     * @return void
     */
    public function testBundleOrderLine(): void
    {
        $variant = new BundleVariant(new ChosenOptions($this->pool([])), new Json());
        $line = $this->orderLine('bundle', [
            'bundle_options' => [
                5 => ['label' => 'Memory', 'value' => [['title' => '16 GB', 'qty' => 1, 'price' => 50]]],
                6 => [
                    'label' => 'Extras',
                    'value' => [['title' => 'Bag', 'qty' => 1], ['title' => 'Mouse', 'qty' => 2]],
                ],
            ],
            'options' => [['label' => 'Note', 'value' => 'Fast']],
        ]);

        self::assertSame('Memory: 16 GB, Extras: Bag, Extras: Mouse, Note: Fast', $variant->variant($line));
    }

    /**
     * A bundle cart line names each child line under the option it was chosen for, then the custom options only
     *
     * @return void
     */
    public function testBundleCartLine(): void
    {
        $pool = $this->pool([['label' => 'Note', 'value' => 'Fast']]);
        $variant = new BundleVariant(new ChosenOptions($pool), new Json());
        $line = $this->cartLine('bundle');
        $line->method('getChildren')->willReturn([
            $this->child('16 GB', '{"qty":1,"option_label":"Memory","option_id":"5"}'),
            $this->child('Bag', '{"qty":1,"option_label":"Extras","option_id":"6"}'),
            $this->child('Orphan', null),
        ]);

        self::assertSame('Memory: 16 GB, Extras: Bag, Note: Fast', $variant->variant($line));
        self::assertSame(['default'], $this->askedTypes);
    }

    /**
     * A strategy returning a fixed text
     *
     * @param string $text
     * @return LineVariantStrategyInterface
     */
    private function fixed(string $text): LineVariantStrategyInterface
    {
        $strategy = $this->createMock(LineVariantStrategyInterface::class);
        $strategy->method('variant')->willReturn($text);

        return $strategy;
    }

    /**
     * A product configuration pool whose configuration lists the given options, recording the types asked for
     *
     * @param list<array<string, mixed>> $options
     * @return ConfigurationPool
     */
    private function pool(array $options): ConfigurationPool
    {
        $configuration = $this->createMock(ConfigurationInterface::class);
        $configuration->method('getOptions')->willReturn($options);
        $pool = $this->createMock(ConfigurationPool::class);
        $pool->method('getByProductType')->willReturnCallback(
            function (string $type) use ($configuration): ConfigurationInterface {
                $this->askedTypes[] = $type;

                return $configuration;
            }
        );

        return $pool;
    }

    /**
     * An order line of the given type with the given product options
     *
     * @param string $type
     * @param array<string, mixed> $productOptions
     * @return OrderItemInterface
     */
    private function orderLine(string $type, array $productOptions): OrderItemInterface
    {
        $line = $this->createMock(OrderItem::class);
        $line->method('getProductType')->willReturn($type);
        $line->method('getProductOptions')->willReturn($productOptions);

        return $line;
    }

    /**
     * A cart line of the given type
     *
     * @param string $type
     * @return QuoteItem&MockObject
     */
    private function cartLine(string $type): CartItemInterface
    {
        $line = $this->createMock(QuoteItem::class);
        $line->method('getProductType')->willReturn($type);

        return $line;
    }

    /**
     * A bundle child line
     *
     * @param string $name
     * @param string|null $selectionAttributes The stored selection attributes; null when the child has none
     * @return QuoteItem
     */
    private function child(string $name, ?string $selectionAttributes): QuoteItem
    {
        $options = [];
        if ($selectionAttributes !== null) {
            $option = $this->createMock(Option::class);
            $option->method('getValue')->willReturn($selectionAttributes);
            $options['bundle_selection_attributes'] = $option;
        }
        $child = $this->createMock(QuoteItem::class);
        $child->method('getName')->willReturn($name);
        $child->method('getOptionsByCode')->willReturn($options);

        return $child;
    }
}
