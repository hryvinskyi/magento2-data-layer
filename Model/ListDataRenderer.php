<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\Browser\ListScriptMarkupInterface;
use Hryvinskyi\DataLayer\Api\EventModifierPipelineInterface;
use Hryvinskyi\DataLayer\Api\HiddenPriceDecision;
use Hryvinskyi\DataLayer\Api\HiddenPricePolicyInterface;
use Hryvinskyi\DataLayer\Api\Url\PathOfUrlInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\ItemInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Hryvinskyi\DataLayerApi\Api\ItemBuilderInterface;
use Hryvinskyi\DataLayerApi\Api\ListDataRendererInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Renders the JSON data script a product list or a list of promotions carries after its own HTML
 *
 * A product list prints `{"list": {...}, "impression": bool, "clicks": bool, "links": {"/path": position}}` in a
 * script marked `data-hryvinskyi-datalayer="list"`. `list` is the ecommerce object of the list's `view_item_list`
 * event, built on the server and run through the event modifiers: what is reported, both as the impression and as the
 * item a click reports. The browser pushes it when `impression` is true (`view_item_list` is enabled) and builds
 * `select_item` from `list.items[position]` when `clicks` is true (`select_item` is enabled). `links` maps the path of
 * each product's URL to its item's position in `list.items`, and is empty when clicks are not reported. A list whose
 * event a modifier drops, or whose prices are hidden in a store that skips such events, is not reported at all:
 * nothing is printed, so neither its impression nor a click on it is.
 *
 * Promotions print `{"promotions": [...], "impression": bool, "clicks": bool, "links": {"/path": position}}` in a
 * script marked `data-hryvinskyi-datalayer="promotions"`, one entry of GA4 promotion parameters per promotion, in
 * display order; the browser pushes one `view_promotion` per promotion and builds `select_promotion` from the entry a
 * clicked link points to. Each promotion's `view_promotion` event goes through the event modifiers and the entry is
 * what they return; a promotion whose event a modifier drops is left out, its impression and its clicks alike.
 *
 * Nothing is printed for an empty list, when nothing is left to report, or when both the impression and the click
 * event are disabled.
 */
class ListDataRenderer implements ListDataRendererInterface
{
    private const VIEW_ITEM_LIST = 'view_item_list';
    private const SELECT_ITEM = 'select_item';
    private const VIEW_PROMOTION = 'view_promotion';
    private const SELECT_PROMOTION = 'select_promotion';

    /**
     * @param EventAvailabilityInterface $eventAvailability
     * @param StoreManagerInterface $storeManager
     * @param ItemSubjectInterfaceFactory $itemSubjectFactory
     * @param ItemBuilderInterface $itemBuilder
     * @param HiddenPricePolicyInterface $hiddenPricePolicy
     * @param EventInterfaceFactory $eventFactory
     * @param EventModifierPipelineInterface $modifiers
     * @param PathOfUrlInterface $pathOfUrl
     * @param SerializerInterface $serializer Encodes JSON safe to print inside a script element
     * @param ListScriptMarkupInterface $markup Wraps the JSON in the data script the browser reads
     */
    public function __construct(
        private readonly EventAvailabilityInterface $eventAvailability,
        private readonly StoreManagerInterface $storeManager,
        private readonly ItemSubjectInterfaceFactory $itemSubjectFactory,
        private readonly ItemBuilderInterface $itemBuilder,
        private readonly HiddenPricePolicyInterface $hiddenPricePolicy,
        private readonly EventInterfaceFactory $eventFactory,
        private readonly EventModifierPipelineInterface $modifiers,
        private readonly PathOfUrlInterface $pathOfUrl,
        private readonly SerializerInterface $serializer,
        private readonly ListScriptMarkupInterface $markup
    ) {
    }

    /**
     * @inheritDoc
     */
    public function renderProductList(string $listId, string $listName, array $products): string
    {
        $products = $this->inOrder($products);
        if ($products === []) {
            return '';
        }
        $impressions = $this->eventAvailability->isEnabled(self::VIEW_ITEM_LIST);
        $clicks = $this->eventAvailability->isEnabled(self::SELECT_ITEM);
        if (!$impressions && !$clicks) {
            return '';
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $subjects = $this->subjects($products, $storeId, $listId, $listName);
        if ($this->hiddenPricePolicy->decide($subjects, $storeId) === HiddenPriceDecision::SkipEvent) {
            return '';
        }
        $items = $this->itemBuilder->build($subjects, self::VIEW_ITEM_LIST);
        if ($items === []) {
            return '';
        }

        $list = $this->modifiers->apply($this->eventFactory->create([
            'name' => self::VIEW_ITEM_LIST,
            'ecommerce' => true,
            'parameters' => ['item_list_id' => $listId, 'item_list_name' => $listName],
            'items' => $items,
        ]));
        if ($list === null || $list->getItems() === []) {
            return '';
        }

        return $this->script('list', [
            'list' => $list->getParameters() + ['items' => $this->itemParameters($list)],
            'impression' => $impressions,
            'clicks' => $clicks,
            'links' => $this->linksObject($clicks ? $this->itemLinks($list, $products) : []),
        ]);
    }

    /**
     * @inheritDoc
     */
    public function renderPromotions(array $promotions): string
    {
        $promotions = $this->inOrder($promotions);
        if ($promotions === []) {
            return '';
        }
        $impressions = $this->eventAvailability->isEnabled(self::VIEW_PROMOTION);
        $clicks = $this->eventAvailability->isEnabled(self::SELECT_PROMOTION);
        if (!$impressions && !$clicks) {
            return '';
        }

        $reported = [];
        $links = [];
        foreach ($promotions as $promotion) {
            $event = $this->modifiers->apply($this->eventFactory->create([
                'name' => self::VIEW_PROMOTION,
                'ecommerce' => true,
                'parameters' => $promotion->toParameters(),
            ]));
            if ($event === null) {
                continue;
            }
            $reported[] = $event->getParameters();
            $path = $this->pathOfUrl->pathOf($promotion->getTargetUrl());
            if ($clicks && $path !== null && !isset($links[$path])) {
                $links[$path] = count($reported) - 1;
            }
        }
        if ($reported === []) {
            return '';
        }

        return $this->script('promotions', [
            'promotions' => $reported,
            'impression' => $impressions,
            'clicks' => $clicks,
            'links' => $this->linksObject($links),
        ]);
    }

    /**
     * Number the entries by display position, whatever keys the caller's array has
     *
     * @template T
     * @param array<T> $entries
     * @return list<T>
     */
    private function inOrder(array $entries): array
    {
        $ordered = [];
        foreach ($entries as $entry) {
            $ordered[] = $entry;
        }

        return $ordered;
    }

    /**
     * Create one item subject per product, at its display position
     *
     * @param list<ProductInterface> $products
     * @param int $storeId
     * @param string $listId
     * @param string $listName
     * @return list<ItemSubjectInterface>
     */
    private function subjects(array $products, int $storeId, string $listId, string $listName): array
    {
        $subjects = [];
        foreach ($products as $index => $product) {
            $subjects[] = $this->itemSubjectFactory->create([
                'product' => $product,
                'storeId' => $storeId,
                'listId' => $listId,
                'listName' => $listName,
                'index' => $index,
            ]);
        }

        return $subjects;
    }

    /**
     * Get the parameters of every item of an event, in order
     *
     * @param EventInterface $event
     * @return list<array<string, string|int|float>>
     */
    private function itemParameters(EventInterface $event): array
    {
        return array_map(static fn (ItemInterface $item): array => $item->getParameters(), $event->getItems());
    }

    /**
     * Map the path of each listed product's URL to the position of its item in the event
     *
     * An item finds its product through its `index` parameter (the product's display position); an item without one,
     * or a product without a URL path, has no link. When two products share a path the first keeps it.
     *
     * @param EventInterface $event
     * @param list<ProductInterface> $products In display order
     * @return array<string, int>
     */
    private function itemLinks(EventInterface $event, array $products): array
    {
        $links = [];
        foreach ($event->getItems() as $position => $item) {
            $index = $item->getParameters()['index'] ?? null;
            $product = is_int($index) ? ($products[$index] ?? null) : null;
            if (!$product instanceof Product) {
                continue;
            }
            $path = $this->pathOfUrl->pathOf((string) $product->getProductUrl());
            if ($path !== null && !isset($links[$path])) {
                $links[$path] = $position;
            }
        }

        return $links;
    }

    /**
     * Make the links encode as a JSON object even when there are none
     *
     * @param array<string,int> $links
     * @return array<string, int>|object
     */
    private function linksObject(array $links): array|object
    {
        return $links === [] ? (object) [] : $links;
    }

    /**
     * Wrap data in the JSON script the browser reads
     *
     * @param string $kind What the script describes: `list` or `promotions`
     * @param array<string,mixed> $data
     * @return string
     * @throws \InvalidArgumentException When the data cannot be encoded
     */
    private function script(string $kind, array $data): string
    {
        $json = $this->serializer->serialize($data);
        if (!is_string($json)) {
            throw new \InvalidArgumentException(
                sprintf('The %s data of a data layer list could not be encoded.', $kind)
            );
        }

        return $this->markup->dataScript($kind, $json);
    }
}
