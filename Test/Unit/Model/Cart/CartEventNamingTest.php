<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Cart;

use Hryvinskyi\DataLayer\Model\Cart\CartEventNaming;
use Hryvinskyi\DataLayerApi\Api\CartKindInterface;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\TestCase;

/**
 * A shopping cart keeps its events' names; a cart of a registered kind takes the first recognising kind's names, or
 * reports nothing; a registration that is not a cart kind fails when the naming is built
 */
class CartEventNamingTest extends TestCase
{
    /**
     * Without kinds every cart is a shopping cart
     *
     * @return void
     */
    public function testWithoutKindsEveryEventKeepsItsName(): void
    {
        $naming = new CartEventNaming();

        self::assertSame('add_to_cart', $naming->eventFor('add_to_cart', $this->cart(42)));
        self::assertSame('begin_checkout', $naming->eventFor('begin_checkout', $this->cart(42)));
    }

    /**
     * A cart no kind recognises keeps its names; a recognised one takes its kind's name, or null for an event its kind
     * does not report
     *
     * @return void
     */
    public function testRecognisedCartTakesItsKindsNames(): void
    {
        $naming = new CartEventNaming([
            'quote' => $this->kind(7, ['add_to_cart' => 'add_to_quote', 'remove_from_cart' => 'remove_from_quote']),
        ]);

        self::assertSame('add_to_quote', $naming->eventFor('add_to_cart', $this->cart(7)));
        self::assertSame('remove_from_quote', $naming->eventFor('remove_from_cart', $this->cart(7)));
        self::assertNull($naming->eventFor('add_payment_info', $this->cart(7)));
        self::assertSame('add_to_cart', $naming->eventFor('add_to_cart', $this->cart(8)));
    }

    /**
     * Kinds are asked in their registered order and the first that recognises the cart answers, even with null
     *
     * @return void
     */
    public function testFirstRecognisingKindAnswers(): void
    {
        $naming = new CartEventNaming([
            'first' => $this->kind(7, []),
            'second' => $this->kind(7, ['view_cart' => 'view_quote']),
            'other' => $this->kind(9, ['view_cart' => 'view_list']),
        ]);

        self::assertNull($naming->eventFor('view_cart', $this->cart(7)));
        self::assertSame('view_list', $naming->eventFor('view_cart', $this->cart(9)));
    }

    /**
     * A registration that is not a cart kind is refused, naming the registration
     *
     * @return void
     */
    public function testRegistrationThatIsNotAKindFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"typo"');

        new CartEventNaming(['typo' => new \stdClass()]);
    }

    /**
     * A cart with an id
     *
     * @param int $id
     * @return CartInterface
     */
    private function cart(int $id): CartInterface
    {
        $cart = $this->createMock(CartInterface::class);
        $cart->method('getId')->willReturn($id);

        return $cart;
    }

    /**
     * A kind that recognises the cart with the given id and names events from a map
     *
     * @param int $cartId
     * @param array<string, string> $events
     * @return CartKindInterface
     */
    private function kind(int $cartId, array $events): CartKindInterface
    {
        return new class ($cartId, $events) implements CartKindInterface {
            /**
             * @param int $cartId
             * @param array<string, string> $events
             */
            public function __construct(private readonly int $cartId, private readonly array $events)
            {
            }

            /**
             * @inheritDoc
             */
            public function isKindOf(CartInterface $cart): bool
            {
                return (int) $cart->getId() === $this->cartId;
            }

            /**
             * @inheritDoc
             */
            public function eventFor(string $cartEvent): ?string
            {
                return $this->events[$cartEvent] ?? null;
            }
        };
    }
}
