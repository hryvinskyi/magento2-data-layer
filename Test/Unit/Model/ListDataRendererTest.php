<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Api\EventModifierPipelineInterface;
use Hryvinskyi\DataLayer\Api\HiddenPriceDecision;
use Hryvinskyi\DataLayer\Api\HiddenPricePolicyInterface;
use Hryvinskyi\DataLayer\Model\Browser\ListScriptMarkup;
use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Model\Data\Item;
use Hryvinskyi\DataLayer\Model\Data\ItemSubject;
use Hryvinskyi\DataLayer\Model\Data\Promotion;
use Hryvinskyi\DataLayer\Model\ListDataRenderer;
use Hryvinskyi\DataLayer\Model\Url\PathOfUrl;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\ItemInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Hryvinskyi\DataLayerApi\Api\ItemBuilderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The list data script: event shape, links by item position, disabled events, hidden prices, event modifiers and
 * promotions; clicks report only what the impression would
 */
class ListDataRendererTest extends TestCase
{
    private const LIST_PREFIX = '<script type="application/json" data-hryvinskyi-datalayer="list">';
    private const PROMOTIONS_PREFIX = '<script type="application/json" data-hryvinskyi-datalayer="promotions">';
    private const SUFFIX = '</script>';

    /**
     * @var list<string>
     */
    private array $disabledEvents = [];

    /**
     * @var HiddenPriceDecision
     */
    private HiddenPriceDecision $decision = HiddenPriceDecision::AllVisible;

    /**
     * @var \Closure(EventInterface): ?EventInterface
     */
    private \Closure $modifier;

    /**
     * @var list<int> Ids of the products the item builder cannot make an item of
     */
    private array $invalidProductIds = [];

    /**
     * @var list<string> Names of the events the modifiers were asked about
     */
    private array $modified = [];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->modifier = static fn (EventInterface $event): EventInterface => $event;
    }

    /**
     * A two-product list prints its view_item_list ecommerce object, the impression flag and the path of each product
     *
     * @return void
     */
    public function testTwoProductListPrintsEventItemsAndLinks(): void
    {
        $html = $this->renderer()->renderProductList('category_12', 'Laptops', $this->twoProducts());

        self::assertSame(
            [
                'list' => [
                    'item_list_id' => 'category_12',
                    'item_list_name' => 'Laptops',
                    'items' => [
                        $this->itemOf(1, 0, 'category_12', 'Laptops'),
                        $this->itemOf(2, 1, 'category_12', 'Laptops'),
                    ],
                ],
                'impression' => true,
                'clicks' => true,
                'links' => ['/laptop-a.html' => 0, '/laptop-b.html' => 1],
            ],
            $this->decode($html, self::LIST_PREFIX)
        );
        self::assertSame(['view_item_list'], $this->modified);
    }

    /**
     * An empty list prints nothing
     *
     * @return void
     */
    public function testEmptyListPrintsNothing(): void
    {
        self::assertSame('', $this->renderer()->renderProductList('category_12', 'Laptops', []));
    }

    /**
     * A list whose products make no item prints nothing
     *
     * @return void
     */
    public function testListWithoutItemsPrintsNothing(): void
    {
        $this->invalidProductIds = [1, 2];

        self::assertSame('', $this->renderer()->renderProductList('category_12', 'Laptops', $this->twoProducts()));
    }

    /**
     * Which events are disabled decides whether anything is printed, whether the list is impressed and its clicks
     * reported
     *
     * @return array<string, array{0: list<string>, 1: bool|null, 2: bool}>
     */
    public static function disabledListEvents(): array
    {
        return [
            'both enabled' => [[], true, true],
            'impressions disabled' => [['view_item_list'], false, true],
            'clicks disabled' => [['select_item'], true, false],
            'both disabled' => [['view_item_list', 'select_item'], null, false],
        ];
    }

    /**
     * Disabled events: nothing printed when both are off, no impression when view_item_list is off, no clicks and no
     * links when select_item is off; the list runs through the modifiers whenever anything is reported
     *
     * @dataProvider disabledListEvents
     * @param list<string> $disabled
     * @param bool|null $impression Null when nothing is printed
     * @param bool $clicks
     * @return void
     */
    #[DataProvider('disabledListEvents')]
    public function testDisabledEvents(array $disabled, ?bool $impression, bool $clicks): void
    {
        $this->disabledEvents = $disabled;

        $html = $this->renderer()->renderProductList('related_products', 'Related Products', $this->twoProducts());

        if ($impression === null) {
            self::assertSame('', $html);
            self::assertSame([], $this->modified);

            return;
        }
        $data = $this->decode($html, self::LIST_PREFIX);
        self::assertSame($impression, $data['impression']);
        self::assertSame($clicks, $data['clicks']);
        self::assertSame($clicks ? ['/laptop-a.html' => 0, '/laptop-b.html' => 1] : [], $data['links']);
        self::assertSame(['view_item_list'], $this->modified);
    }

    /**
     * Clicks disabled print an empty links object, never a JSON array
     *
     * @return void
     */
    public function testDisabledClicksPrintAnEmptyLinksObject(): void
    {
        $this->disabledEvents = ['select_item'];

        $html = $this->renderer()->renderProductList('category_12', 'Laptops', $this->twoProducts());

        self::assertStringContainsString('"clicks":false,"links":{}', $html);
    }

    /**
     * A hidden price with the skip setting reports nothing about the list: no impression, no clicks, no modifier run
     *
     * @return void
     */
    public function testHiddenPriceSkipPrintsNothing(): void
    {
        $this->decision = HiddenPriceDecision::SkipEvent;

        self::assertSame('', $this->renderer()->renderProductList('category_12', 'Laptops', $this->twoProducts()));
        self::assertSame([], $this->modified);
    }

    /**
     * A modifier that drops the list event drops its clicks too: nothing is printed
     *
     * @return void
     */
    public function testDroppedEventPrintsNothing(): void
    {
        $this->modifier = static fn (EventInterface $event): ?EventInterface => null;

        self::assertSame('', $this->renderer()->renderProductList('category_12', 'Laptops', $this->twoProducts()));
    }

    /**
     * A click reports the item as the modifiers left it, and links point into the modified list
     *
     * @return void
     */
    public function testLinksPointIntoTheModifiedList(): void
    {
        $this->modifier = static fn (EventInterface $event): EventInterface => new Event(
            $event->getName(),
            true,
            $event->getParameters(),
            array_values(array_filter(
                $event->getItems(),
                static fn (ItemInterface $item): bool => $item->getParameters()['item_id'] !== 'SKU1'
            ))
        );

        $data = $this->decode(
            $this->renderer()->renderProductList('category_12', 'Laptops', $this->twoProducts()),
            self::LIST_PREFIX
        );

        self::assertSame(['/laptop-b.html' => 0], $data['links']);
        self::assertSame([$this->itemOf(2, 1, 'category_12', 'Laptops')], $this->listOf($data)['items']);
    }

    /**
     * A modifier's replacement is what the browser pushes
     *
     * @return void
     */
    public function testModifiedEventIsPrinted(): void
    {
        $this->modifier = static fn (EventInterface $event): EventInterface => new Event(
            $event->getName(),
            true,
            $event->getParameters() + ['currency' => 'GBP'],
            $event->getItems()
        );

        $data = $this->decode(
            $this->renderer()->renderProductList('category_12', 'Laptops', $this->twoProducts()),
            self::LIST_PREFIX
        );

        self::assertTrue($data['impression']);
        self::assertSame('GBP', $this->listOf($data)['currency']);
    }

    /**
     * A product left out of the items has no link, and the links follow the remaining items' positions
     *
     * @return void
     */
    public function testLinksFollowItemPositionsWhenAProductIsLeftOut(): void
    {
        $this->invalidProductIds = [1];

        $data = $this->decode(
            $this->renderer()->renderProductList('category_12', 'Laptops', $this->twoProducts()),
            self::LIST_PREFIX
        );

        self::assertSame(['/laptop-b.html' => 0], $data['links']);
        self::assertSame([$this->itemOf(2, 1, 'category_12', 'Laptops')], $this->listOf($data)['items']);
    }

    /**
     * Products without a usable URL give an empty links object, never a JSON array
     *
     * @return void
     */
    public function testNoLinksPrintAnEmptyObject(): void
    {
        $html = $this->renderer()->renderProductList('category_12', 'Laptops', [$this->product(1, '')]);

        self::assertStringContainsString('"links":{}', $html);
    }

    /**
     * Markup in a list name cannot close the script element
     *
     * @return void
     */
    public function testMarkupInNamesCannotEscapeTheScript(): void
    {
        $html = $this->renderer()->renderProductList(
            'category_12',
            '</script><b>Laptops',
            $this->twoProducts()
        );

        self::assertSame(1, substr_count($html, self::SUFFIX));
        self::assertStringEndsWith(self::SUFFIX, $html);
        $list = $this->listOf($this->decode($html, self::LIST_PREFIX));
        self::assertSame('</script><b>Laptops', $list['item_list_name']);
    }

    /**
     * Promotions print their GA4 parameters in order, the impression flag and the path of each target link
     *
     * @return void
     */
    public function testPromotionsPrintParametersAndLinks(): void
    {
        $data = $this->decode($this->renderer()->renderPromotions($this->promotions()), self::PROMOTIONS_PREFIX);

        self::assertSame(
            [
                'promotions' => [
                    ['promotion_id' => 'summer', 'promotion_name' => 'Summer sale', 'creative_slot' => 'top'],
                    ['promotion_id' => 'laptops', 'promotion_name' => 'Laptops'],
                    ['promotion_name' => 'No link'],
                ],
                'impression' => true,
                'clicks' => true,
                'links' => ['/sale.html' => 0, '/laptops/' => 1],
            ],
            $data
        );
        self::assertSame(['view_promotion', 'view_promotion', 'view_promotion'], $this->modified);
    }

    /**
     * No promotions, or both promotion events disabled, print nothing
     *
     * @return void
     */
    public function testPromotionsPrintNothingWhenEmptyOrDisabled(): void
    {
        self::assertSame('', $this->renderer()->renderPromotions([]));

        $this->disabledEvents = ['view_promotion', 'select_promotion'];
        self::assertSame('', $this->renderer()->renderPromotions($this->promotions()));
    }

    /**
     * With view_promotion disabled the promotions, as the modifiers leave them, are kept for clicks without an
     * impression
     *
     * @return void
     */
    public function testPromotionsWithoutImpressionsKeepLinks(): void
    {
        $this->disabledEvents = ['view_promotion'];

        $data = $this->decode($this->renderer()->renderPromotions($this->promotions()), self::PROMOTIONS_PREFIX);

        self::assertFalse($data['impression']);
        self::assertTrue($data['clicks']);
        self::assertSame(['/sale.html' => 0, '/laptops/' => 1], $data['links']);
        self::assertSame(['view_promotion', 'view_promotion', 'view_promotion'], $this->modified);
    }

    /**
     * With select_promotion disabled the promotions are impressed and print no links
     *
     * @return void
     */
    public function testPromotionsWithoutClicksPrintNoLinks(): void
    {
        $this->disabledEvents = ['select_promotion'];

        $html = $this->renderer()->renderPromotions($this->promotions());
        $data = $this->decode($html, self::PROMOTIONS_PREFIX);

        self::assertTrue($data['impression']);
        self::assertFalse($data['clicks']);
        self::assertStringContainsString('"links":{}', $html);
    }

    /**
     * A promotion whose event a modifier drops is left out, its impression and its clicks alike; the links point into
     * what is left
     *
     * @return void
     */
    public function testDroppedPromotionIsLeftOut(): void
    {
        $this->modifier = static fn (EventInterface $event): ?EventInterface =>
            ($event->getParameters()['promotion_id'] ?? '') === 'summer'
                ? null
                : new Event($event->getName(), true, $event->getParameters() + ['creative_name' => 'changed']);

        $data = $this->decode($this->renderer()->renderPromotions($this->promotions()), self::PROMOTIONS_PREFIX);

        self::assertTrue($data['impression']);
        self::assertSame(
            [
                ['promotion_id' => 'laptops', 'promotion_name' => 'Laptops', 'creative_name' => 'changed'],
                ['promotion_name' => 'No link', 'creative_name' => 'changed'],
            ],
            $data['promotions']
        );
        self::assertSame(['/laptops/' => 0], $data['links']);
    }

    /**
     * Promotions whose events the modifiers all drop print nothing
     *
     * @return void
     */
    public function testEveryPromotionDroppedPrintsNothing(): void
    {
        $this->modifier = static fn (EventInterface $event): ?EventInterface => null;

        self::assertSame('', $this->renderer()->renderPromotions($this->promotions()));
    }

    /**
     * A modifier's changes to the promotions are printed when every event survives
     *
     * @return void
     */
    public function testModifiedPromotionsArePrinted(): void
    {
        $this->modifier = static fn (EventInterface $event): EventInterface =>
            new Event($event->getName(), true, $event->getParameters() + ['creative_name' => 'changed']);

        $data = $this->decode($this->renderer()->renderPromotions($this->promotions()), self::PROMOTIONS_PREFIX);

        self::assertTrue($data['impression']);
        self::assertSame(
            [
                [
                    'promotion_id' => 'summer',
                    'promotion_name' => 'Summer sale',
                    'creative_slot' => 'top',
                    'creative_name' => 'changed',
                ],
                ['promotion_id' => 'laptops', 'promotion_name' => 'Laptops', 'creative_name' => 'changed'],
                ['promotion_name' => 'No link', 'creative_name' => 'changed'],
            ],
            $data['promotions']
        );
    }

    /**
     * Build the renderer with fakes for every collaborator but the value objects, the path service and the encoder
     *
     * @return ListDataRenderer
     */
    private function renderer(): ListDataRenderer
    {
        $availability = $this->createMock(EventAvailabilityInterface::class);
        $availability->method('isEnabled')->willReturnCallback(
            fn (string $eventName): bool => !in_array($eventName, $this->disabledEvents, true)
        );
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $subjectFactory = $this->createMock(ItemSubjectInterfaceFactory::class);
        $subjectFactory->method('create')->willReturnCallback(
            static function (array $data): ItemSubjectInterface {
                self::assertInstanceOf(ProductInterface::class, $data['product']);
                self::assertIsInt($data['storeId']);
                self::assertIsString($data['listId']);
                self::assertIsString($data['listName']);
                self::assertIsInt($data['index']);

                return new ItemSubject(
                    $data['product'],
                    $data['storeId'],
                    listId: $data['listId'],
                    listName: $data['listName'],
                    index: $data['index']
                );
            }
        );
        $eventFactory = $this->createMock(EventInterfaceFactory::class);
        $eventFactory->method('create')->willReturnCallback(
            static function (array $data): EventInterface {
                self::assertIsString($data['name']);
                self::assertIsBool($data['ecommerce']);
                self::assertIsArray($data['parameters']);

                $items = (array) ($data['items'] ?? []);

                return new Event($data['name'], $data['ecommerce'], $data['parameters'], $items);
            }
        );
        $hiddenPrices = $this->createMock(HiddenPricePolicyInterface::class);
        $hiddenPrices->method('decide')->willReturnCallback(fn (): HiddenPriceDecision => $this->decision);
        $modifiers = $this->createMock(EventModifierPipelineInterface::class);
        $modifiers->method('apply')->willReturnCallback(
            function (EventInterface $event): ?EventInterface {
                $this->modified[] = $event->getName();

                return ($this->modifier)($event);
            }
        );

        return new ListDataRenderer(
            $availability,
            $storeManager,
            $subjectFactory,
            $this->itemBuilder(),
            $hiddenPrices,
            $eventFactory,
            $modifiers,
            new PathOfUrl(),
            new JsonHexTag(),
            new ListScriptMarkup()
        );
    }

    /**
     * An item builder that makes one item per subject from its product id and list data
     *
     * @return ItemBuilderInterface
     */
    private function itemBuilder(): ItemBuilderInterface
    {
        $builder = $this->createMock(ItemBuilderInterface::class);
        $builder->method('build')->willReturnCallback(function (array $subjects): array {
            $items = [];
            foreach ($subjects as $subject) {
                self::assertInstanceOf(ItemSubjectInterface::class, $subject);
                $productId = (int) $subject->getProduct()->getId();
                if (in_array($productId, $this->invalidProductIds, true)) {
                    continue;
                }
                $items[] = new Item($this->itemOf(
                    $productId,
                    (int) $subject->getIndex(),
                    (string) $subject->getListId(),
                    (string) $subject->getListName()
                ));
            }

            return $items;
        });

        return $builder;
    }

    /**
     * The parameters the fake builder gives a product's item
     *
     * @param int $productId
     * @param int $index
     * @param string $listId
     * @param string $listName
     * @return array<string, string|int|float>
     */
    private function itemOf(int $productId, int $index, string $listId, string $listName): array
    {
        return [
            'item_id' => 'SKU' . $productId,
            'item_name' => 'Laptop ' . $productId,
            'price' => 99.5 + $productId,
            'item_list_id' => $listId,
            'item_list_name' => $listName,
            'index' => $index,
        ];
    }

    /**
     * Two listed products with their URLs
     *
     * @return list<Product>
     */
    private function twoProducts(): array
    {
        return [
            $this->product(1, 'https://shop.test/laptop-a.html?utm_source=x'),
            $this->product(2, 'https://shop.test/laptop-b.html'),
        ];
    }

    /**
     * A product with an id and a URL
     *
     * @param int $id
     * @param string $url
     * @return Product
     */
    private function product(int $id, string $url): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getProductUrl')->willReturn($url);

        return $product;
    }

    /**
     * Three promotions: two with a link, one without
     *
     * @return list<Promotion>
     */
    private function promotions(): array
    {
        return [
            new Promotion('summer', 'Summer sale', '', 'top', '', 'https://shop.test/sale.html?b=1'),
            new Promotion('laptops', 'Laptops', '', '', '', '/laptops/'),
            new Promotion('', 'No link'),
        ];
    }

    /**
     * Decode a printed data script, checking its markup
     *
     * @param string $html
     * @param non-empty-string $prefix The opening tag the script must have
     * @return array<mixed>
     */
    private function decode(string $html, string $prefix): array
    {
        self::assertStringStartsWith($prefix, $html);
        self::assertStringEndsWith(self::SUFFIX, $html);
        $data = json_decode(substr($html, strlen($prefix), -strlen(self::SUFFIX)), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    /**
     * Get the list object of decoded list data
     *
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private function listOf(array $data): array
    {
        self::assertIsArray($data['list']);

        return $data['list'];
    }
}
