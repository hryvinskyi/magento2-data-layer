<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Model\Data\Item;
use Hryvinskyi\DataLayer\Model\HiddenPricePolicy;
use Hryvinskyi\DataLayer\Model\ItemBuilder;
use Hryvinskyi\DataLayer\Test\Unit\Model\ItemField\SubjectDoubles;
use Hryvinskyi\DataLayerApi\Api\Data\ItemInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Hryvinskyi\DataLayerApi\Api\ItemFieldProviderInterface;
use Hryvinskyi\DataLayerApi\Api\PriceVisibilityInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use PHPUnit\Framework\TestCase;

/**
 * Every provider is asked once with all subjects, later providers win, hidden money is removed (never from order
 * lines), and neither a failing provider nor an invalid subject stops the event
 */
class ItemBuilderTest extends TestCase
{
    use SubjectDoubles;

    /**
     * @var RecordingLogger
     */
    private RecordingLogger $logger;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    /**
     * Each provider is called once with every subject; a later provider replaces an earlier one's parameter
     *
     * @return void
     */
    public function testProvidersRunOnceInOrderAndLaterOnesWin(): void
    {
        $calls = [];
        $first = $this->fixedProvider([0 => ['item_id' => 'A', 'price' => 1.0], 1 => ['item_id' => 'B']], $calls);
        $second = $this->fixedProvider([0 => ['price' => 2.0, 'item_brand' => 'Acme']], $calls);

        $items = $this->builder([$first, $second])->build(
            [$this->subject($this->product(1)), $this->subject($this->product(2))],
            'view_item_list'
        );

        self::assertSame(
            [['item_id' => 'A', 'price' => 2.0, 'item_brand' => 'Acme'], ['item_id' => 'B']],
            $this->parameters($items)
        );
        self::assertSame([[2, 'view_item_list'], [2, 'view_item_list']], $calls);
    }

    /**
     * A hidden price removes price and discount from catalogue and cart line items, never from order line items
     *
     * @return void
     */
    public function testHiddenMoneyIsRemovedExceptFromOrderLines(): void
    {
        $calls = [];
        $money = ['item_id' => 'X', 'price' => 10.0, 'discount' => 1.0, 'quantity' => 1];
        $provider = $this->fixedProvider([0 => $money, 1 => $money, 2 => $money, 3 => $money], $calls);
        $hidden = $this->product(1);

        $items = $this->builder([$provider], [1])->build(
            [
                $this->subject($hidden),
                $this->subject($hidden, ['cart' => $this->cartLine()]),
                $this->subject($hidden, ['order' => $this->orderLine()]),
                $this->subject($this->product(2)),
            ],
            'purchase'
        );

        self::assertSame(
            [
                ['item_id' => 'X', 'quantity' => 1],
                ['item_id' => 'X', 'quantity' => 1],
                $money,
                $money,
            ],
            $this->parameters($items)
        );
    }

    /**
     * A subject without item_id and item_name is left out and logged with its product and the event
     *
     * @return void
     */
    public function testInvalidSubjectIsLeftOutAndLogged(): void
    {
        $calls = [];
        $provider = $this->fixedProvider([0 => ['item_id' => 'A'], 1 => ['price' => 5.0]], $calls);

        $items = $this->builder([$provider])->build(
            [$this->subject($this->product(1)), $this->subject($this->product(7))],
            'view_cart'
        );

        self::assertSame([['item_id' => 'A']], $this->parameters($items));
        self::assertCount(1, $this->logger->records);
        self::assertSame('view_cart', $this->logger->records[0]['context']['event']);
        self::assertSame(7, $this->logger->records[0]['context']['product_id']);
    }

    /**
     * A provider that throws is logged with its class and the event; the others still fill the items
     *
     * @return void
     */
    public function testFailingProviderIsLoggedAndSkipped(): void
    {
        $calls = [];
        $failing = $this->createMock(ItemFieldProviderInterface::class);
        $failing->method('provide')->willThrowException(new \RuntimeException('database gone'));

        $items = $this->builder([$failing, $this->fixedProvider([0 => ['item_id' => 'A']], $calls)])
            ->build([$this->subject($this->product(1))], 'view_item');

        self::assertSame([['item_id' => 'A']], $this->parameters($items));
        self::assertCount(1, $this->logger->records);
        self::assertSame($failing::class, $this->logger->records[0]['context']['provider']);
        self::assertSame('view_item', $this->logger->records[0]['context']['event']);
    }

    /**
     * A provider that is not an item field provider is refused when the builder is created
     *
     * @return void
     */
    public function testForeignProviderIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->builder([new \stdClass()]);
    }

    /**
     * A provider returning fixed parameters and recording how it was called
     *
     * @param array<int, array<string, string|int|float>> $fields
     * @param list<array{int, string}> $calls Receives the number of subjects and the event of each call
     * @return ItemFieldProviderInterface
     */
    private function fixedProvider(array $fields, array &$calls): ItemFieldProviderInterface
    {
        $provider = $this->createMock(ItemFieldProviderInterface::class);
        $provider->method('provide')->willReturnCallback(
            static function (array $subjects, string $eventName) use ($fields, &$calls): array {
                $calls[] = [count($subjects), $eventName];

                return $fields;
            }
        );

        return $provider;
    }

    /**
     * A builder over the given providers, creating real items, with the given products' prices hidden
     *
     * @param array<mixed> $providers
     * @param list<int> $hiddenProductIds
     * @return ItemBuilder
     */
    private function builder(array $providers, array $hiddenProductIds = []): ItemBuilder
    {
        $factory = $this->createMock(ItemInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn (array $data): ItemInterface => new Item(
                is_array($data['parameters']) ? $data['parameters'] : []
            )
        );
        $visibility = $this->createMock(PriceVisibilityInterface::class);
        $visibility->method('isPriceVisible')->willReturnCallback(
            static fn (ProductInterface $product): bool => !in_array((int) $product->getId(), $hiddenProductIds, true)
        );

        return new ItemBuilder(
            $factory,
            new HiddenPricePolicy($visibility, $this->settings()),
            $this->logger,
            $providers
        );
    }

    /**
     * The parameters of each built item
     *
     * @param list<ItemInterface> $items
     * @return list<array<string, string|int|float>>
     */
    private function parameters(array $items): array
    {
        return array_map(static fn (ItemInterface $item): array => $item->getParameters(), $items);
    }
}
