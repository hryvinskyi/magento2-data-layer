<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\Sidebar;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\SalesRule\Model\ResourceModel\Rule as RuleResource;
use Magento\SalesRule\Model\Rule;
use PHPUnit\Framework\TestCase;

/**
 * Events detected on the server wait in the visitor's queue for the browser: a sign-in, and a cart whose product is
 * added, raised from the mini cart, re-saved with its options and removed, each reported exactly once with the units
 * that changed; a line just added or raised is priced for its whole quantity, and a quantity changed from the cart page
 * is reported at the catalogue price for its new quantity with no discount until the cart's totals run again
 *
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class ServerEventsTest extends TestCase
{
    use ResolvesServices;
    use TakesQueuedEvents;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->service(CheckoutSession::class)->clearQuote();
        $this->takeQueuedEvents();
    }

    /**
     * A sign-in is handed to the browser once, then the queue is empty
     *
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testSignInIsHandedOverOnce(): void
    {
        $customer = $this->fresh(Customer::class);
        $this->service(CustomerResource::class)->load($customer, 1);

        $this->service(ManagerInterface::class)->dispatch('customer_login', ['customer' => $customer]);

        self::assertSame([['event' => 'login', 'method' => 'website']], $this->waitingEvents());
        self::assertSame([], $this->waitingEvents());
    }

    /**
     * Adding two units, raising the line to three from the mini cart and setting it to zero report exactly one event
     * each, with the units that changed
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testCartChangesReportTheUnitsThatChanged(): void
    {
        $cart = $this->service(Cart::class);
        $cart->addProduct($this->sellableProduct('DL-SRV-1', 'Server event laptop', 25.0), ['qty' => 2]);
        $cart->save();
        self::assertSame([['add_to_cart', 2]], $this->namesAndQuantities($this->waitingEvents()));

        $line = $cart->getQuote()->getAllVisibleItems()[0] ?? null;
        self::assertNotNull($line);
        $lineId = $line->getId();
        self::assertIsNumeric($lineId);
        $lineId = (int) $lineId;

        $this->startRequestWith($cart);
        $this->service(Sidebar::class)->updateQuoteItem($lineId, 3);
        self::assertSame([['add_to_cart', 1]], $this->namesAndQuantities($this->waitingEvents()));

        $this->startRequestWith($cart);
        $cart->updateItems([$lineId => ['qty' => 0]]);
        self::assertSame([['remove_from_cart', 3]], $this->namesAndQuantities($this->waitingEvents()));
    }

    /**
     * Re-saving a line with its options (the product page's cart line edit) reports only the real change against the
     * quantity the line was loaded with: 2 → 3 adds one, an unchanged re-save reports nothing, 3 → 1 removes two
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testReSavingALineReportsOnlyTheChange(): void
    {
        $cart = $this->fresh(Cart::class);
        $cart->addProduct($this->sellableProduct('DL-SRV-2', 'Server event dock', 25.0), ['qty' => 2]);
        $cart->save();
        $cartId = $cart->getQuote()->getId();
        self::assertIsNumeric($cartId);
        $cartId = (int) $cartId;
        $lineId = $this->onlyLineId($cart->getQuote());
        $this->waitingEvents();

        $this->reSave($cartId, $lineId, 3);
        self::assertSame([['add_to_cart', 1]], $this->namesAndQuantities($this->waitingEvents()));

        $this->reSave($cartId, $lineId, 3);
        self::assertSame([], $this->waitingEvents());

        $this->reSave($cartId, $lineId, 1);
        self::assertSame([['remove_from_cart', 2]], $this->namesAndQuantities($this->waitingEvents()));
    }

    /**
     * A line just added reports the catalogue price for its whole quantity (the tier price), and the event's value is
     * that price times the units, before the cart's totals ran for the line
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testNewLineReportsTheTierPriceForItsQuantity(): void
    {
        $product = $this->sellableProduct('DL-SRV-3', 'Server event cable', 25.0, [3 => 20.0]);

        $this->fresh(Cart::class)->addProduct($product, ['qty' => 3]);

        $events = $this->waitingEvents();
        self::assertCount(1, $events);
        $ecommerce = is_array($events[0]) ? $events[0]['ecommerce'] ?? null : null;
        self::assertIsArray($ecommerce);
        self::assertSame(60.0, $ecommerce['value'] ?? null);
        $items = $ecommerce['items'] ?? null;
        self::assertIsArray($items);
        $item = reset($items);
        self::assertIsArray($item);
        self::assertSame(20.0, $item['price'] ?? null);
    }

    /**
     * Raising a line of two to three by adding one more unit reports that unit at the tier price for three
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testRaisedLineReportsTheTierPriceForItsNewQuantity(): void
    {
        $product = $this->sellableProduct('DL-SRV-4', 'Server event charger', 25.0, [3 => 20.0]);
        $cart = $this->fresh(Cart::class);
        $cart->addProduct($product, ['qty' => 2]);
        $cart->save();
        $this->waitingEvents();

        $this->startRequestWith($cart);
        $cart->addProduct($product, ['qty' => 1]);

        $ecommerce = $this->onlyEcommerce('add_to_cart');
        self::assertSame(20.0, $ecommerce['value'] ?? null);
        self::assertSame(['price' => 20.0, 'quantity' => 1], $this->moneyAndQuantity($ecommerce));
    }

    /**
     * A line of three at 20 with a fixed discount of 30 lowered to one on the cart page reports the two units removed
     * at the catalogue price for one unit, without the discount calculated for three units: the discount is not
     * re-applied to the new quantity and the value is never negative
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testLoweredLineIsReportedBeforeItsCartTotalsRun(): void
    {
        $this->fixedCartDiscount(30.0);
        $cart = $this->fresh(Cart::class);
        $cart->addProduct($this->sellableProduct('DL-SRV-5', 'Server event monitor', 20.0), ['qty' => 3]);
        $cart->save();
        $lineId = $this->onlyLineId($cart->getQuote());
        $line = $cart->getQuote()->getItemById($lineId);
        self::assertNotFalse($line);
        $discount = $line->getDiscountAmount();
        self::assertIsNumeric($discount);
        self::assertEqualsWithDelta(30.0, (float) $discount, 0.001);
        $this->waitingEvents();

        $this->startRequestWith($cart);
        $cart->updateItems([$lineId => ['qty' => 1]]);

        $ecommerce = $this->onlyEcommerce('remove_from_cart');
        self::assertSame(40.0, $ecommerce['value'] ?? null);
        self::assertSame(['price' => 20.0, 'quantity' => 2], $this->moneyAndQuantity($ecommerce));
    }

    /**
     * Take the waiting events, expect exactly one with the given name, and get its ecommerce object
     *
     * @param string $name
     * @return array<mixed>
     */
    private function onlyEcommerce(string $name): array
    {
        $events = $this->waitingEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertIsArray($event);
        self::assertSame($name, $event['event'] ?? null);
        $ecommerce = $event['ecommerce'] ?? null;
        self::assertIsArray($ecommerce);

        return $ecommerce;
    }

    /**
     * Get the money and quantity parameters of an ecommerce object's only item
     *
     * @param array<mixed> $ecommerce
     * @return array<string, mixed> The item's `price`, `discount` and `quantity`, those it has
     */
    private function moneyAndQuantity(array $ecommerce): array
    {
        $items = $ecommerce['items'] ?? null;
        self::assertIsArray($items);
        self::assertCount(1, $items);
        $item = reset($items);
        self::assertIsArray($item);

        return array_intersect_key($item, ['price' => true, 'discount' => true, 'quantity' => true]);
    }

    /**
     * Create an active cart rule without a coupon that takes a fixed amount off the whole cart of a guest
     *
     * @param float $amount
     * @return void
     */
    private function fixedCartDiscount(float $amount): void
    {
        $rule = $this->fresh(Rule::class);
        $rule->setData([
            'name' => 'Data layer fixed cart discount',
            'is_active' => 1,
            'customer_group_ids' => [0],
            'website_ids' => [1],
            'coupon_type' => Rule::COUPON_TYPE_NO_COUPON,
            'simple_action' => Rule::CART_FIXED_ACTION,
            'discount_amount' => $amount,
            'stop_rules_processing' => 0,
        ]);
        $this->service(RuleResource::class)->save($rule);
    }

    /**
     * Load the cart as a new request does, re-save its line with a new quantity as editing the line does, and save
     *
     * @param int $cartId
     * @param int $lineId
     * @param int $quantity
     * @return void
     */
    private function reSave(int $cartId, int $lineId, int $quantity): void
    {
        $quote = $this->fresh(Quote::class);
        $resource = $this->service(QuoteResource::class);
        $resource->load($quote, $cartId);
        $quote->updateItem($lineId, $this->fresh(DataObject::class)->setData(['id' => $lineId, 'qty' => $quantity]));
        $quote->collectTotals();
        $resource->save($quote);
    }

    /**
     * Get the id of the cart's only visible line
     *
     * @param Quote $quote
     * @return int
     */
    private function onlyLineId(Quote $quote): int
    {
        $lines = $quote->getAllVisibleItems();
        self::assertCount(1, $lines);
        $lineId = reset($lines)->getId();
        self::assertIsNumeric($lineId);

        return (int) $lineId;
    }

    /**
     * Give the cart its lines as a new request's cart has them
     *
     * The cart repository loads a cart with its visible lines as its items, and saving the cart writes those items
     * back. A cart that stays in memory across steps keeps the items its first save left, so each step starts the
     * way the next request would.
     *
     * @param Cart $cart
     * @return void
     */
    private function startRequestWith(Cart $cart): void
    {
        $quote = $cart->getQuote();
        $quote->setItems($quote->getAllVisibleItems());
    }

    /**
     * Take the events waiting for the browser, as a page does: read them, then acknowledge them
     *
     * @return list<mixed>
     */
    private function waitingEvents(): array
    {
        return $this->takeQueuedEvents();
    }

    /**
     * Reduce ecommerce events to their name and the quantity of their one item
     *
     * @param list<mixed> $events
     * @return list<array{mixed, mixed}>
     */
    private function namesAndQuantities(array $events): array
    {
        $reduced = [];
        foreach ($events as $event) {
            self::assertIsArray($event);
            $ecommerce = $event['ecommerce'] ?? null;
            self::assertIsArray($ecommerce);
            $items = $ecommerce['items'] ?? null;
            self::assertIsArray($items);
            self::assertCount(1, $items);
            $item = reset($items);
            self::assertIsArray($item);
            $reduced[] = [$event['event'] ?? null, $item['quantity'] ?? null];
        }

        return $reduced;
    }

    /**
     * Create an enabled simple product that can be added to a cart without stock, and load it for store view 1
     *
     * @param string $sku
     * @param string $name
     * @param float $price
     * @param array<int, float> $tierPrices Price for all customer groups by quantity
     * @return Product
     */
    private function sellableProduct(string $sku, string $name, float $price, array $tierPrices = []): Product
    {
        $product = $this->fresh(Product::class);
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setSku($sku)
            ->setName($name)
            ->setPrice($price)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setWebsiteIds([1])
            ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0]);
        $tiers = [];
        foreach ($tierPrices as $quantity => $tierPrice) {
            $tiers[] = ['website_id' => 0, 'cust_group' => 32000, 'price_qty' => $quantity, 'price' => $tierPrice];
        }
        if ($tiers !== []) {
            $product->setData('tier_price', $tiers);
        }
        $resource = $this->service(ProductResource::class);
        $resource->save($product);

        $loaded = $this->fresh(Product::class);
        $loaded->setStoreId(1);
        $resource->load($loaded, (int) $product->getId());

        return $loaded;
    }
}
