<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration\Event;

use Hryvinskyi\DataLayer\Api\Event\PurchaseEventBuilderInterface;
use Hryvinskyi\DataLayer\Model\Event\PurchaseEventBuilder;
use Hryvinskyi\DataLayer\Model\HiddenPricePolicy;
use Hryvinskyi\DataLayer\Model\ItemBuilder;
use Hryvinskyi\DataLayer\Model\PriceVisibility;
use Hryvinskyi\DataLayer\Test\Integration\Catalog\CatalogFixtures;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemInterface;
use Hryvinskyi\DataLayerApi\Api\PriceVisibilityInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager as TestObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * The container-wired purchase builder reports a real order read back from the database, with every line's product
 * loaded in one product query
 *
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 * @magentoDataFixture Magento/Catalog/_files/categories_no_products.php
 */
class PurchaseEventBuilderTest extends TestCase
{
    use CatalogFixtures;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->cleanCategoryTrees();
        $this->forgetSessionCart();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->forgetSessionCart();
        $this->forgetPricingServices();
    }

    /**
     * Drop the checkout session's cart
     *
     * An order save can make other modules' observers save the session's cart; after a rolled-back test that cart
     * keeps the id of a row that no longer exists, and saving what refers to it fails the next test's order save.
     *
     * @return void
     */
    private function forgetSessionCart(): void
    {
        $this->service(CheckoutSession::class)->clearQuote();
    }

    /**
     * The builder interface resolves to the purchase builder
     *
     * @return void
     */
    public function testBuilderResolves(): void
    {
        self::assertInstanceOf(PurchaseEventBuilder::class, $this->service(PurchaseEventBuilderInterface::class));
    }

    /**
     * A three-line order reports its id, store, currency, money, coupon and lines
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/purchase/include_tax 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/purchase/include_shipping 1
     * @return void
     */
    public function testOrderIsReported(): void
    {
        $order = $this->placedOrder([
            ['DL-T5-A', 'Order laptop', 2, 40.0],
            ['DL-T5-B', 'Order mouse', 1, 5.0],
            ['DL-T5-C', 'Order bag', 1, 15.0],
        ]);

        [$event, $productQueries] = $this->buildCountingProductQueries($order);

        self::assertNotNull($event);
        self::assertSame('purchase', $event->getName());
        self::assertSame(
            [
                'transaction_id' => 'DLT5-100',
                'affiliation' => $this->storeName(),
                'currency' => 'USD',
                'value' => 125.0,
                'tax' => 15.0,
                'shipping' => 10.0,
                'coupon' => 'SAVE5',
            ],
            $event->getParameters()
        );
        self::assertSame(
            [
                ['DL-T5-A', 'Order laptop', 40.0, 2],
                ['DL-T5-B', 'Order mouse', 5.0, 1],
                ['DL-T5-C', 'Order bag', 15.0, 1],
            ],
            array_map(
                static fn (ItemInterface $item): array => [
                    $item->getParameters()['item_id'] ?? null,
                    $item->getParameters()['item_name'] ?? null,
                    $item->getParameters()['price'] ?? null,
                    $item->getParameters()['quantity'] ?? null,
                ],
                $event->getItems()
            )
        );
        self::assertSame(1, $productQueries, 'every line product is loaded in one product query');
    }

    /**
     * The value basis and the tax deduction are the order's store view's
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/purchase/deduct_tax 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/purchase/include_tax 0
     * @magentoConfigFixture current_store hryvinskyi_datalayer/purchase/include_shipping 0
     * @return void
     */
    public function testValueLessTax(): void
    {
        $order = $this->placedOrder([['DL-T5-D', 'Order dock', 1, 100.0]]);

        [$event] = $this->buildCountingProductQueries($order);

        self::assertNotNull($event);
        self::assertSame(110.0, $event->getParameters()['value'] ?? null);
        self::assertArrayNotHasKey('tax', $event->getParameters());
        self::assertArrayNotHasKey('shipping', $event->getParameters());
    }

    /**
     * With a price visibility contribution that hides every price and a store that skips such events, the purchase
     * still carries its value, tax, shipping and item prices: an order is a business record
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/purchase/include_tax 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/purchase/include_shipping 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/item/hidden_price_handling skip_event
     * @return void
     */
    public function testHiddenPricesDoNotStripAnOrder(): void
    {
        $this->hideEveryPrice();
        $order = $this->placedOrder([['DL-T5-F', 'Order screen', 1, 100.0]]);

        [$event] = $this->buildCountingProductQueries($order);

        self::assertNotNull($event);
        self::assertSame(
            ['value' => 125.0, 'tax' => 15.0, 'shipping' => 10.0],
            array_intersect_key($event->getParameters(), ['value' => true, 'tax' => true, 'shipping' => true])
        );
        self::assertSame(100.0, ($event->getItems()[0] ?? null)?->getParameters()['price'] ?? null);
    }

    /**
     * A store view without the data layer reports nothing
     *
     * @return void
     */
    public function testDisabledStoreReportsNothing(): void
    {
        $order = $this->placedOrder([['DL-T5-E', 'Order cable', 1, 100.0]]);

        self::assertNull($this->service(PurchaseEventBuilderInterface::class)->build($order));
    }

    /**
     * Save an order of store view 1 with the given lines and read it back from the database
     *
     * Totals: subtotal 100, tax 15, shipping 10, grand total 125, coupon SAVE5, currency USD.
     *
     * @param list<array{string, string, int, float}> $lines SKU, name, quantity and unit price of each line
     * @return OrderInterface
     */
    private function placedOrder(array $lines): OrderInterface
    {
        $order = $this->fresh(Order::class);
        $order->setIncrementId('DLT5-100')
            ->setState(Order::STATE_PROCESSING)
            ->setStatus('processing')
            ->setStoreId(1)
            ->setSubtotal(100)
            ->setBaseSubtotal(100)
            ->setTaxAmount(15)
            ->setBaseTaxAmount(15)
            ->setShippingAmount(10)
            ->setBaseShippingAmount(10)
            ->setGrandTotal(125)
            ->setBaseGrandTotal(125)
            ->setCouponCode('SAVE5')
            ->setOrderCurrencyCode('USD')
            ->setBaseCurrencyCode('USD')
            ->setCustomerIsGuest(1)
            ->setCustomerEmail('buyer@example.test');
        foreach (['billing', 'shipping'] as $type) {
            $address = $this->fresh(OrderAddress::class);
            $address->setAddressType($type)
                ->setFirstname('Test')
                ->setLastname('Buyer')
                ->setStreet(['1 Test Street'])
                ->setCity('Testville')
                ->setPostcode('12345')
                ->setCountryId('US')
                ->setRegionId(12)
                ->setTelephone('5555555')
                ->setEmail('buyer@example.test');
            if ($type === 'billing') {
                $order->setBillingAddress($address);
                continue;
            }
            $order->setShippingAddress($address);
        }
        $payment = $this->fresh(Payment::class);
        $payment->setMethod('checkmo');
        $order->setPayment($payment);

        foreach ($lines as [$sku, $name, $quantity, $price]) {
            $item = $this->fresh(OrderItem::class);
            $item->setProductId($this->createProduct($sku, 'Catalogue ' . $name))
                ->setSku($sku)
                ->setName($name)
                ->setProductType('simple')
                ->setQtyOrdered($quantity)
                ->setPrice($price)
                ->setBasePrice($price)
                ->setRowTotal($price * $quantity)
                ->setBaseRowTotal($price * $quantity);
            $order->addItem($item);
        }

        $repository = $this->service(OrderRepositoryInterface::class);
        $repository->save($order);

        $orderId = $order->getEntityId();

        return $this->fresh(OrderRepositoryInterface::class)->get(is_numeric($orderId) ? (int) $orderId : 0);
    }

    /**
     * Build the purchase and count the product collection queries it issues
     *
     * @param OrderInterface $order
     * @return array{EventInterface|null, int}
     */
    private function buildCountingProductQueries(OrderInterface $order): array
    {
        $connection = $this->service(ResourceConnection::class)->getConnection();
        self::assertInstanceOf(\Zend_Db_Adapter_Abstract::class, $connection);
        $profiler = $connection->getProfiler();
        $profiler->setEnabled(true);
        $profiler->clear();
        try {
            $event = $this->service(PurchaseEventBuilderInterface::class)->build($order);
            $productQueries = 0;
            foreach ($profiler->getQueryProfiles() ?: [] as $profile) {
                if ($profile instanceof \Zend_Db_Profiler_Query
                    && preg_match('/FROM \x60catalog_product_entity\x60 AS \x60e\x60/', $profile->getQuery()) === 1
                ) {
                    $productQueries++;
                }
            }

            return [$event, $productQueries];
        } finally {
            $profiler->setEnabled(false);
        }
    }

    /**
     * Rebuild the pricing services around a price visibility whose one contribution hides every price
     *
     * @return void
     */
    private function hideEveryPrice(): void
    {
        $this->forgetPricingServices();
        $hiding = new class implements PriceVisibilityInterface {
            /**
             * @inheritDoc
             */
            public function isPriceVisible(ProductInterface $product): bool
            {
                return false;
            }
        };
        $visibility = Bootstrap::getObjectManager()->create(
            PriceVisibility::class,
            ['contributions' => ['hide_every_price' => $hiding]]
        );
        $this->testObjectManager()->addSharedInstance(
            $this->checked($visibility, PriceVisibility::class),
            PriceVisibility::class
        );
    }

    /**
     * Drop the shared pricing services, so the next request for them is built from the container's configuration
     *
     * @return void
     */
    private function forgetPricingServices(): void
    {
        $objectManager = $this->testObjectManager();
        $types = [PriceVisibility::class, HiddenPricePolicy::class, ItemBuilder::class, PurchaseEventBuilder::class];
        foreach ($types as $type) {
            $objectManager->removeSharedInstance($type);
        }
    }

    /**
     * Get the test framework's object manager
     *
     * @return TestObjectManager
     */
    private function testObjectManager(): TestObjectManager
    {
        return $this->checked(Bootstrap::getObjectManager(), TestObjectManager::class);
    }

    /**
     * Get the frontend name of store view 1
     *
     * @return string
     */
    private function storeName(): string
    {
        $store = $this->service(StoreManagerInterface::class)->getStore(1);

        return $store instanceof Store ? $store->getFrontendName() : (string) $store->getName();
    }
}
