<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use Hryvinskyi\DataLayer\Api\Cart\CartEventNamingInterface;
use Hryvinskyi\DataLayer\Api\Event\CartValueInterface;
use Hryvinskyi\DataLayer\Api\HiddenPriceDecision;
use Hryvinskyi\DataLayer\Api\HiddenPricePolicyInterface;
use Hryvinskyi\DataLayer\Model\Cart\CartEventNaming;
use Hryvinskyi\DataLayer\Model\Event\EcommerceEventAssembler;
use Hryvinskyi\DataLayer\Model\Event\ItemsValue;
use Hryvinskyi\DataLayerApi\Api\CartKindInterface;
use Hryvinskyi\DataLayerApi\Api\CartTrackingPolicyInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Server-detected ecommerce events: nothing is built for a disabled event or an untracked cart, a cart reports its
 * events under the names its kind gives them, quantities and value follow the units the event is about, and money
 * follows the hidden-price decision
 */
class EcommerceEventAssemblerTest extends TestCase
{
    use ServerEventDoubles;

    /**
     * @var RecordingItemBuilder
     */
    private RecordingItemBuilder $items;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->items = new RecordingItemBuilder(25.0);
    }

    /**
     * A disabled event builds no subject and no item
     *
     * @return void
     */
    public function testDisabledEventDoesNoWork(): void
    {
        $subjects = $this->createMock(ItemSubjectInterfaceFactory::class);
        $subjects->expects(self::never())->method('create');
        $assembler = $this->assembler(enabled: false, subjects: $subjects);
        $cart = $this->cart();

        self::assertNull($assembler->forCartLine('add_to_cart', $this->line($this->catalogueProduct(1), $cart), 2.0));
        self::assertNull($assembler->forCart('add_shipping_info', $this->cart([], [
            $this->line($this->catalogueProduct(1), $cart),
        ])));
        self::assertNull($assembler->forProduct('add_to_wishlist', $this->catalogueProduct(1), 1.0, 1));
        self::assertSame([], $this->items->calls);
    }

    /**
     * A cart that is not tracked makes no cart event
     *
     * @return void
     */
    public function testUntrackedCartMakesNoEvent(): void
    {
        $assembler = $this->assembler(tracked: false);
        $cart = $this->cart();
        $line = $this->line($this->catalogueProduct(1), $cart);

        self::assertNull($assembler->forCartLine('add_to_cart', $line, 1.0));
        self::assertNull($assembler->forCart('add_payment_info', $this->cart([], [$line])));
        self::assertSame([], $this->items->calls);
    }

    /**
     * A cart of a registered kind reports a line event and a whole-cart event under its kind's names, and the event is
     * checked for the store view under the name it is reported by
     *
     * @return void
     */
    public function testCartOfAKindReportsItsOwnEvents(): void
    {
        $asked = [];
        $quote = $this->cart(['kind' => 'quote', 'quote_currency_code' => 'EUR']);
        $line = $this->line($this->catalogueProduct(5), $quote, 2.0);
        $assembler = $this->assembler(naming: $this->quoteNaming(), asked: $asked);

        $added = $this->built($assembler->forCartLine('add_to_cart', $line, 2.0));
        $viewed = $this->built($assembler->forCart('view_cart', $this->cart(['kind' => 'quote'], [$line])));

        self::assertSame('add_to_quote', $added->getName());
        self::assertSame(50.0, $added->getParameters()['value']);
        self::assertSame('view_quote', $viewed->getName());
        self::assertSame(['add_to_quote', 'view_quote'], $asked);
        self::assertSame(['add_to_quote', 'view_quote'], array_column($this->items->calls, 1));
    }

    /**
     * A cart whose kind reports no such event makes none and does no work for it, while a shopping cart in the same
     * request keeps the shopping cart's names
     *
     * @return void
     */
    public function testCartOfAKindWithoutSuchEventMakesNone(): void
    {
        $asked = [];
        $assembler = $this->assembler(naming: $this->quoteNaming(), asked: $asked);
        $quote = $this->cart(['kind' => 'quote']);
        $quoteLine = $this->line($this->catalogueProduct(1), $quote);

        self::assertNull($assembler->forCart('add_shipping_info', $this->cart(['kind' => 'quote'], [$quoteLine])));
        self::assertNull($assembler->forCart('begin_checkout', $this->cart(['kind' => 'quote'], [$quoteLine])));
        self::assertSame([], $asked);
        self::assertSame([], $this->items->calls);

        $cartLine = $this->line($this->catalogueProduct(1), $this->cart());
        $event = $this->built($assembler->forCartLine('add_to_cart', $cartLine, 1.0));
        self::assertSame('add_to_cart', $event->getName());
    }

    /**
     * A line event reports the units it is about, and its value is the unit price times those units
     *
     * @return void
     */
    public function testLineEventReportsTheUnitsItIsAbout(): void
    {
        $cart = $this->cart(['quote_currency_code' => 'EUR']);
        $line = $this->line($this->catalogueProduct(5), $cart, 7.0);

        $event = $this->built($this->assembler()->forCartLine('remove_from_cart', $line, 3.0));

        self::assertSame(
            [
                'event' => 'remove_from_cart',
                'ecommerce' => [
                    'currency' => 'EUR',
                    'value' => 75.0,
                    'items' => [['item_id' => 'SKU-5', 'price' => 25.0, 'quantity' => 3.0]],
                ],
            ],
            $event->toDataLayer()
        );
        self::assertSame($line, $this->items->calls[0][0][0]->getQuoteItem());
    }

    /**
     * A configurable line's only child line gives the chosen variant; a cart that was never saved reports the store
     * view's currency
     *
     * @return void
     */
    public function testLineWithOneChildReportsItsVariantAndAnUnsavedCartTheStoreCurrency(): void
    {
        $cart = $this->cart();
        $child = $this->line($this->catalogueProduct(9), $cart);
        $line = $this->line($this->catalogueProduct(8), $cart, 1.0, [$child]);

        $event = $this->built($this->assembler()->forCartLine('add_to_cart', $line, 1.0));

        self::assertSame('USD', $event->getParameters()['currency']);
        $subject = $this->items->calls[0][0][0];
        self::assertSame(9, $subject->getChildProduct()?->getId());
        self::assertSame(8, $subject->getProduct()->getId());
    }

    /**
     * No units, no event
     *
     * @return void
     */
    public function testNoUnitsNoEvent(): void
    {
        $cart = $this->cart();

        $line = $this->line($this->catalogueProduct(1), $cart);

        self::assertNull($this->assembler()->forCartLine('add_to_cart', $line, 0.0));
        self::assertNull($this->assembler()->forProduct('add_to_wishlist', $this->catalogueProduct(1), -1.0, 1));
    }

    /**
     * A hidden price leaves the value out, or skips the event, as the decision says
     *
     * @return void
     */
    public function testHiddenPriceOmitsTheValueOrSkipsTheEvent(): void
    {
        $cart = $this->cart(['quote_currency_code' => 'EUR', 'subtotal' => 99.0]);
        $line = $this->line($this->catalogueProduct(1), $cart, 2.0);

        $omitted = $this->built(
            $this->assembler(decision: HiddenPriceDecision::OmitMoney)->forCartLine('add_to_cart', $line, 2.0)
        );
        self::assertSame(['currency' => 'EUR'], $omitted->getParameters());
        $omittedCart = $this->built(
            $this->assembler(decision: HiddenPriceDecision::OmitMoney)->forCart('add_shipping_info', $this->cart(
                ['quote_currency_code' => 'EUR', 'subtotal' => 99.0],
                [$line]
            ))
        );
        self::assertArrayNotHasKey('value', $omittedCart->getParameters());

        self::assertNull(
            $this->assembler(decision: HiddenPriceDecision::SkipEvent)->forCartLine('add_to_cart', $line, 2.0)
        );
    }

    /**
     * A cart event has one item per visible line with the line's quantity, the items' value (not the stored
     * subtotal), the coupon, and the step's own parameters
     *
     * @return void
     */
    public function testCartEventReportsEveryLine(): void
    {
        $owner = $this->cart();
        $lines = [
            $this->line($this->catalogueProduct(1), $owner, 2.0),
            $this->line($this->catalogueProduct(2), $owner, 1.0),
        ];
        $cart = $this->cart(
            ['quote_currency_code' => 'EUR', 'subtotal' => 61.5, 'coupon_code' => ' SAVE10 '],
            $lines
        );

        $event = $this->built($this->assembler()->forCart('add_shipping_info', $cart, ['shipping_tier' => 'Flat']));

        self::assertSame(
            ['currency' => 'EUR', 'value' => 75.0, 'coupon' => 'SAVE10', 'shipping_tier' => 'Flat'],
            $event->getParameters()
        );
        self::assertSame([2.0, 1.0], array_map(
            static fn ($item): mixed => $item->getParameters()['quantity'],
            $event->getItems()
        ));
    }

    /**
     * A cart event given a cart value reports that value, worked out from the cart and the built items; with a hidden
     * price the value is left out and never worked out
     *
     * @return void
     */
    public function testCartEventGivenACartValueReportsIt(): void
    {
        $owner = $this->cart();
        $cart = $this->cart(
            ['quote_currency_code' => 'EUR', 'grand_total' => 131.2],
            [$this->line($this->catalogueProduct(1), $owner, 2.0)]
        );
        $asked = [];
        $value = $this->createMock(CartValueInterface::class);
        $value->method('valueOf')->willReturnCallback(
            static function (CartInterface $valued, array $items) use (&$asked): float {
                $asked[] = [$valued, count($items)];

                return 131.2;
            }
        );

        $event = $this->built($this->assembler()->forCart('add_payment_info', $cart, [], $value));
        $omitted = $this->built(
            $this->assembler(decision: HiddenPriceDecision::OmitMoney)->forCart('add_payment_info', $cart, [], $value)
        );

        self::assertSame(['currency' => 'EUR', 'value' => 131.2], $event->getParameters());
        self::assertSame([[$cart, 1]], $asked);
        self::assertArrayNotHasKey('value', $omitted->getParameters());
    }

    /**
     * An empty cart makes no event
     *
     * @return void
     */
    public function testEmptyCartMakesNoEvent(): void
    {
        self::assertNull($this->assembler()->forCart('add_payment_info', $this->cart(['subtotal' => 0])));
    }

    /**
     * A product event reports the store view's currency and the price times the quantity
     *
     * @return void
     */
    public function testProductEvent(): void
    {
        $event = $this->built($this->assembler()->forProduct('add_to_wishlist', $this->catalogueProduct(3), 2.0, 1));

        self::assertSame(['currency' => 'USD', 'value' => 50.0], $event->getParameters());
        self::assertSame(1, $this->items->calls[0][0][0]->getStoreId());
    }

    /**
     * The assembler with the given decisions
     *
     * @param bool $enabled
     * @param bool $tracked
     * @param HiddenPriceDecision $decision
     * @param ItemSubjectInterfaceFactory|null $subjects
     * @param CartEventNamingInterface|null $naming Every cart a shopping cart when none is given
     * @param list<string> $asked Receives every event name the store view's availability is asked for
     * @return EcommerceEventAssembler
     */
    private function assembler(
        bool $enabled = true,
        bool $tracked = true,
        HiddenPriceDecision $decision = HiddenPriceDecision::AllVisible,
        ?ItemSubjectInterfaceFactory $subjects = null,
        ?CartEventNamingInterface $naming = null,
        array &$asked = []
    ): EcommerceEventAssembler {
        $availability = $this->createMock(EventAvailabilityInterface::class);
        $availability->method('isEnabled')->willReturnCallback(
            static function (string $eventName) use ($enabled, &$asked): bool {
                $asked[] = $eventName;

                return $enabled;
            }
        );
        $tracking = $this->createMock(CartTrackingPolicyInterface::class);
        $tracking->method('isTracked')->willReturn($tracked);
        $hidden = $this->createMock(HiddenPricePolicyInterface::class);
        $hidden->method('decide')->willReturn($decision);
        $store = $this->createMock(Store::class);
        $store->method('getCurrentCurrencyCode')->willReturn('USD');
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return new EcommerceEventAssembler(
            $availability,
            $tracking,
            $hidden,
            $this->items,
            $subjects ?? $this->subjectFactory(),
            $this->eventFactory(),
            $stores,
            new ItemsValue(),
            $naming ?? new CartEventNaming()
        );
    }

    /**
     * Cart naming with one kind: a cart marked as a quote reports its line events and its page as quote events, and
     * nothing else
     *
     * @return CartEventNaming
     */
    private function quoteNaming(): CartEventNaming
    {
        return new CartEventNaming(['quote' => new class implements CartKindInterface {
            /**
             * @inheritDoc
             */
            public function isKindOf(CartInterface $cart): bool
            {
                return $cart instanceof Quote && $cart->getData('kind') === 'quote';
            }

            /**
             * @inheritDoc
             */
            public function eventFor(string $cartEvent): ?string
            {
                return ['add_to_cart' => 'add_to_quote', 'view_cart' => 'view_quote'][$cartEvent] ?? null;
            }
        }]);
    }
}
