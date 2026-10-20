<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration;

use Hryvinskyi\DataLayer\Api\Cart\RepricedLinesInterface;
use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\Event\ItemsValueInterface;
use Hryvinskyi\DataLayer\Api\EventModifierPipelineInterface;
use Hryvinskyi\DataLayer\Api\KnownEventsInterface;
use Hryvinskyi\DataLayer\Api\Product\QuantityPriceInfoInterface;
use Hryvinskyi\DataLayer\Api\Product\TaxBasisPriceInterface;
use Hryvinskyi\DataLayer\Api\ServerEventSignalInterface;
use Hryvinskyi\DataLayer\Model\Cart\RepricedLines;
use Hryvinskyi\DataLayer\Model\CartTrackingPolicy;
use Hryvinskyi\DataLayer\Model\Config;
use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Model\Data\Item;
use Hryvinskyi\DataLayer\Model\Data\ItemSubject;
use Hryvinskyi\DataLayer\Model\Data\Promotion;
use Hryvinskyi\DataLayer\Model\Data\VisitorContext;
use Hryvinskyi\DataLayer\Model\Event\ItemsValue;
use Hryvinskyi\DataLayer\Model\EventAvailability;
use Hryvinskyi\DataLayer\Model\EventChannelPolicy;
use Hryvinskyi\DataLayer\Model\EventDispatcher;
use Hryvinskyi\DataLayer\Model\EventModifierPipeline;
use Hryvinskyi\DataLayer\Model\KnownEvents;
use Hryvinskyi\DataLayer\Model\NoServerEventSignal;
use Hryvinskyi\DataLayer\Model\NoVisitorContext;
use Hryvinskyi\DataLayer\Model\PriceVisibility;
use Hryvinskyi\DataLayer\Model\Product\Price\QuantityPriceInfo;
use Hryvinskyi\DataLayer\Model\Product\Price\TaxBasisPrice;
use Hryvinskyi\DataLayerApi\Api\CartTrackingPolicyInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\ItemInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\PromotionInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\VisitorContextInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Hryvinskyi\DataLayerApi\Api\EventChannelPolicyInterface;
use Hryvinskyi\DataLayerApi\Api\EventDispatcherInterface;
use Hryvinskyi\DataLayerApi\Api\PriceVisibilityInterface;
use Hryvinskyi\DataLayerApi\Api\VisitorContextProviderInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Model\Product;
use Magento\Framework\Session\Generic;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use PHPUnit\Framework\TestCase;

/**
 * Every contract this module implements resolves through the real container to its implementation, the generated
 * factories build valid objects and refuse invalid ones, and the data layer's session namespace is its own
 */
class WiringTest extends TestCase
{
    use ResolvesServices;

    /**
     * Each service contract resolves to this module's implementation
     *
     * The visitor provider is meant to be replaced by a module that captures visitor identity, and the server-event
     * signal by a module that delivers events to the browser, so only their resolving is pinned; the core's own
     * provider, built directly, knows no visitor, and its own signal, built directly, signals nothing.
     *
     * @return void
     */
    public function testEveryServiceContractResolvesToThisModule(): void
    {
        foreach ([
            ConfigInterface::class => Config::class,
            KnownEventsInterface::class => KnownEvents::class,
            EventModifierPipelineInterface::class => EventModifierPipeline::class,
            EventAvailabilityInterface::class => EventAvailability::class,
            PriceVisibilityInterface::class => PriceVisibility::class,
            CartTrackingPolicyInterface::class => CartTrackingPolicy::class,
            EventChannelPolicyInterface::class => EventChannelPolicy::class,
            EventDispatcherInterface::class => EventDispatcher::class,
            ItemsValueInterface::class => ItemsValue::class,
            RepricedLinesInterface::class => RepricedLines::class,
            TaxBasisPriceInterface::class => TaxBasisPrice::class,
            QuantityPriceInfoInterface::class => QuantityPriceInfo::class,
        ] as $contract => $implementation) {
            self::assertInstanceOf($implementation, $this->service($contract), $contract);
        }

        self::assertInstanceOf(
            VisitorContextProviderInterface::class,
            \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(VisitorContextProviderInterface::class)
        );
        $noVisitor = $this->fresh(NoVisitorContext::class);
        self::assertNull($noVisitor->current());
        self::assertNull($noVisitor->forOrder($this->service(OrderInterfaceFactory::class)->create()));

        self::assertInstanceOf(
            ServerEventSignalInterface::class,
            \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(ServerEventSignalInterface::class)
        );
        $noSignal = $this->fresh(NoServerEventSignal::class);
        $noSignal->signal();
        $noSignal->clear();
        self::assertFalse($noSignal->canSignal());
    }

    /**
     * The core knows the events it reports, each with a label
     *
     * @return void
     */
    public function testCoreEventsAreKnown(): void
    {
        $knownEvents = $this->service(KnownEventsInterface::class);

        foreach ([
            'view_item_list', 'select_item', 'view_item', 'add_to_cart', 'remove_from_cart', 'view_cart',
            'begin_checkout', 'add_shipping_info', 'add_payment_info', 'purchase', 'add_to_wishlist', 'login',
            'sign_up', 'view_promotion', 'select_promotion',
        ] as $eventName) {
            self::assertTrue($knownEvents->isKnown($eventName), $eventName);
            self::assertNotSame('', $knownEvents->getLabels()[$eventName] ?? '', $eventName);
        }
    }

    /**
     * Of the core's events, exactly the ones the shopper's browser detects are marked so, in the answer by name and
     * in the descriptions the generated factory builds; what the server detects and order events are not
     *
     * @return void
     */
    public function testOnlyBrowserDetectedCoreEventsAreBrowserOriginated(): void
    {
        $knownEvents = $this->service(KnownEventsInterface::class);
        $browser = [
            'view_item_list', 'select_item', 'view_item', 'view_cart', 'begin_checkout', 'view_promotion',
            'select_promotion',
        ];
        $server = [
            'add_to_cart', 'remove_from_cart', 'add_shipping_info', 'add_payment_info', 'purchase', 'ads_conversion',
            'add_to_wishlist', 'login', 'sign_up',
        ];
        $described = [];
        foreach ($knownEvents->getEvents() as $event) {
            $described[$event->getName()] = $event->isBrowserOriginated();
        }

        foreach ($browser as $eventName) {
            self::assertTrue($knownEvents->isBrowserOriginated($eventName), $eventName);
            self::assertTrue($described[$eventName] ?? null, $eventName);
        }
        foreach ($server as $eventName) {
            self::assertFalse($knownEvents->isBrowserOriginated($eventName), $eventName);
            self::assertFalse($described[$eventName] ?? null, $eventName);
        }
    }

    /**
     * The generated factories build this module's data objects from named arguments
     *
     * @return void
     */
    public function testGeneratedFactoriesBuildValidObjects(): void
    {
        $item = $this->service(ItemInterfaceFactory::class)->create(
            ['parameters' => ['item_id' => 'SKU-1', 'price' => 9.99]]
        );
        $event = $this->service(EventInterfaceFactory::class)->create(
            ['name' => 'view_item', 'ecommerce' => true, 'parameters' => ['currency' => 'GBP'], 'items' => [$item]]
        );
        $product = $this->service(ProductInterfaceFactory::class)->create();
        $subject = $this->service(ItemSubjectInterfaceFactory::class)->create(
            ['product' => $product, 'storeId' => 1, 'quantity' => 2, 'listId' => 'related']
        );
        $promotion = $this->service(PromotionInterfaceFactory::class)->create(
            ['promotionId' => 'banner_1', 'creativeSlot' => 'hero']
        );
        $visitor = $this->service(VisitorContextInterfaceFactory::class)->create(
            ['occurredAt' => new \DateTimeImmutable(), 'clientId' => '123.456']
        );

        self::assertInstanceOf(Item::class, $item);
        self::assertInstanceOf(Event::class, $event);
        self::assertSame(
            ['event' => 'view_item', 'ecommerce' => ['currency' => 'GBP', 'items' => [$item->getParameters()]]],
            $event->toDataLayer()
        );
        self::assertInstanceOf(ItemSubject::class, $subject);
        self::assertInstanceOf(Product::class, $subject->getProduct());
        self::assertNull($subject->getChildProduct());
        self::assertSame(2.0, $subject->getQuantity());
        self::assertSame('related', $subject->getListId());
        self::assertInstanceOf(Promotion::class, $promotion);
        self::assertSame(['promotion_id' => 'banner_1', 'creative_slot' => 'hero'], $promotion->toParameters());
        self::assertInstanceOf(VisitorContext::class, $visitor);
        self::assertSame('123.456', $visitor->getClientId());
        self::assertSame([], $visitor->getClickIds());
    }

    /**
     * A generated factory refuses an invalid event just as the constructor does
     *
     * @return void
     */
    public function testGeneratedFactoryRefusesAnInvalidEvent(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service(EventInterfaceFactory::class)->create(
            ['name' => 'Add To Cart', 'ecommerce' => true]
        );
    }

    /**
     * The visitor provider of a store without server-side delivery knows nobody
     *
     * @return void
     */
    public function testNoVisitorIsKnownWithoutServerSideDelivery(): void
    {
        $visitors = $this->service(VisitorContextProviderInterface::class);

        self::assertNull($visitors->current());
        self::assertNull($visitors->forOrder($this->service(OrderInterfaceFactory::class)->create()));
    }

    /**
     * The data layer's session storage keeps its values in a namespace of its own, shared by every instance
     *
     * @magentoAppArea frontend
     * @return void
     */
    public function testSessionStorageHasItsOwnNamespace(): void
    {
        $storage = $this->fresh(Generic::class, 'HryvinskyiDataLayerSessionStorage');

        $storage->setData('hryvinskyi_datalayer_probe', 'kept');

        $again = $this->fresh(Generic::class, 'HryvinskyiDataLayerSessionStorage');
        self::assertSame('kept', $again->getData('hryvinskyi_datalayer_probe'));
        self::assertNull($this->fresh(Generic::class)->getData('hryvinskyi_datalayer_probe'));

        $storage->unsetData('hryvinskyi_datalayer_probe');
    }
}
