<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event;

use Hryvinskyi\DataLayer\Api\Event\ViewItemEventBuilderInterface;
use Hryvinskyi\DataLayer\Api\HiddenPriceDecision;
use Hryvinskyi\DataLayer\Api\HiddenPricePolicyInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Hryvinskyi\DataLayerApi\Api\ItemBuilderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds `view_item` for the product a product page shows, in the current store view
 *
 * The item is the product itself with quantity 1: the page shows one product, whatever quantity the shopper may go
 * on to choose. `currency` is the store view's current currency and `value` the item's price, which is left out
 * (with the item's own money) when the viewer may not see the price.
 */
class ViewItemEventBuilder implements ViewItemEventBuilderInterface
{
    public const EVENT_NAME = 'view_item';

    /**
     * @param EventAvailabilityInterface $availability
     * @param HiddenPricePolicyInterface $hiddenPricePolicy
     * @param ItemBuilderInterface $itemBuilder
     * @param ItemSubjectInterfaceFactory $subjectFactory
     * @param EventInterfaceFactory $eventFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly EventAvailabilityInterface $availability,
        private readonly HiddenPricePolicyInterface $hiddenPricePolicy,
        private readonly ItemBuilderInterface $itemBuilder,
        private readonly ItemSubjectInterfaceFactory $subjectFactory,
        private readonly EventInterfaceFactory $eventFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function build(ProductInterface $product): ?EventInterface
    {
        $store = $this->storeManager->getStore();
        $storeId = (int) $store->getId();
        if (!$this->availability->isEnabled(self::EVENT_NAME, $storeId)) {
            return null;
        }

        $subjects = [$this->subjectFactory->create(['product' => $product, 'storeId' => $storeId, 'quantity' => 1.0])];
        $decision = $this->hiddenPricePolicy->decide($subjects, $storeId);
        if ($decision === HiddenPriceDecision::SkipEvent) {
            return null;
        }

        $items = $this->itemBuilder->build($subjects, self::EVENT_NAME);
        if ($items === []) {
            return null;
        }

        $parameters = [];
        $currency = $store instanceof Store ? (string) $store->getCurrentCurrencyCode() : '';
        if ($currency !== '') {
            $parameters['currency'] = $currency;
        }
        $price = $items[0]->getParameters()['price'] ?? null;
        if ($decision === HiddenPriceDecision::AllVisible && is_numeric($price)) {
            $parameters['value'] = round((float) $price, 2);
        }

        return $this->eventFactory->create([
            'name' => self::EVENT_NAME,
            'ecommerce' => true,
            'parameters' => $parameters,
            'items' => $items,
        ]);
    }
}
