<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use Hryvinskyi\DataLayer\Api\Event\CartValueInterface;
use Hryvinskyi\DataLayer\Api\Event\EcommerceEventAssemblerInterface;
use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Model\Event\AddPaymentInfoEventBuilder;
use Hryvinskyi\DataLayer\Model\Event\AddShippingInfoEventBuilder;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The checkout steps report the cart with the step's own parameter: the shipping method's description (or code), the
 * payment method's title (or code); the cart is valued by the checkout step value; a disabled step reads nothing
 */
class CheckoutStepEventBuildersTest extends TestCase
{
    /**
     * @var list<array{string, array<mixed>}>
     */
    private array $assembled = [];

    /**
     * @var list<CartValueInterface|null>
     */
    private array $values = [];

    /**
     * @var CartValueInterface|null
     */
    private ?CartValueInterface $stepValue = null;

    /**
     * A disabled step does not read the cart or ask for the cart event
     *
     * @return void
     */
    public function testDisabledStepDoesNoWork(): void
    {
        $cart = $this->createMock(Quote::class);
        $cart->method('getStoreId')->willReturn(1);
        $cart->expects(self::never())->method('getShippingAddress');
        $cart->expects(self::never())->method('getPayment');

        $shipping = new AddShippingInfoEventBuilder($this->availability(false), $this->assembler(), $this->stepValue());
        $payment = new AddPaymentInfoEventBuilder($this->availability(false), $this->assembler(), $this->stepValue());

        self::assertNull($shipping->build($cart));
        self::assertNull($payment->build($cart));
        self::assertSame([], $this->assembled);
    }

    /**
     * The shipping tier is the method's description, or its code when it has none
     *
     * @return void
     */
    public function testShippingTier(): void
    {
        $builder = new AddShippingInfoEventBuilder($this->availability(true), $this->assembler(), $this->stepValue());

        $builder->build($this->cartShippedBy(['shipping_description' => 'Flat Rate', 'shipping_method' => 'f_f']));
        $builder->build($this->cartShippedBy(['shipping_description' => ' ', 'shipping_method' => 'flat_flat']));
        $builder->build($this->cartShippedBy([]));

        self::assertSame(
            [
                ['add_shipping_info', ['shipping_tier' => 'Flat Rate']],
                ['add_shipping_info', ['shipping_tier' => 'flat_flat']],
                ['add_shipping_info', []],
            ],
            $this->assembled
        );
    }

    /**
     * The payment type is the method's title, or its code when the method cannot give one
     *
     * @return void
     */
    public function testPaymentType(): void
    {
        $builder = new AddPaymentInfoEventBuilder($this->availability(true), $this->assembler(), $this->stepValue());

        $builder->build($this->cartPaidBy('checkmo', 'Check / Money order'));
        $builder->build($this->cartPaidBy('gone', null));
        $builder->build($this->cartPaidBy('', 'Never asked'));

        self::assertSame(
            [
                ['add_payment_info', ['payment_type' => 'Check / Money order']],
                ['add_payment_info', ['payment_type' => 'gone']],
                ['add_payment_info', []],
            ],
            $this->assembled
        );
    }

    /**
     * Both steps value the cart with the checkout step value they were given, not the assembler's items value
     *
     * @return void
     */
    public function testStepsAreValuedByTheCheckoutStepValue(): void
    {
        $stepValue = $this->stepValue();
        (new AddShippingInfoEventBuilder($this->availability(true), $this->assembler(), $stepValue))
            ->build($this->cartShippedBy([]));
        (new AddPaymentInfoEventBuilder($this->availability(true), $this->assembler(), $stepValue))
            ->build($this->cartPaidBy('checkmo', 'Check / Money order'));

        self::assertSame([$stepValue, $stepValue], $this->values);
    }

    /**
     * The checkout step value of this test, the same one each time it is asked for
     *
     * @return CartValueInterface
     */
    private function stepValue(): CartValueInterface
    {
        return $this->stepValue ??= $this->createStub(CartValueInterface::class);
    }

    /**
     * A cart whose shipping address holds the given data
     *
     * @param array<string, string> $data
     * @return Quote&MockObject
     */
    private function cartShippedBy(array $data): Quote
    {
        $address = $this->createMock(Address::class);
        $address->method('getData')->willReturnCallback(static fn (string $key = ''): mixed => $data[$key] ?? null);
        $cart = $this->createMock(Quote::class);
        $cart->method('getStoreId')->willReturn(1);
        $cart->method('getShippingAddress')->willReturn($address);

        return $cart;
    }

    /**
     * A cart paid by a method whose title is the given one, or whose method cannot be found
     *
     * @param string $code
     * @param string|null $title Null for a method that cannot be found
     * @return Quote&MockObject
     */
    private function cartPaidBy(string $code, ?string $title): Quote
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($code);
        if ($title === null) {
            $payment->method('getMethodInstance')
                ->willThrowException(new LocalizedException(__('Not available')));
        } else {
            $payment->method('getMethodInstance')->willReturn(new DataObject(['title' => $title]));
        }
        $cart = $this->createMock(Quote::class);
        $cart->method('getStoreId')->willReturn(1);
        $cart->method('getPayment')->willReturn($payment);

        return $cart;
    }

    /**
     * Event availability that answers the same for every event
     *
     * @param bool $enabled
     * @return EventAvailabilityInterface
     */
    private function availability(bool $enabled): EventAvailabilityInterface
    {
        $availability = $this->createMock(EventAvailabilityInterface::class);
        $availability->method('isEnabled')->willReturn($enabled);

        return $availability;
    }

    /**
     * An assembler that records each cart event it is asked for
     *
     * @return EcommerceEventAssemblerInterface
     */
    private function assembler(): EcommerceEventAssemblerInterface
    {
        $assembler = $this->createMock(EcommerceEventAssemblerInterface::class);
        $assembler->method('forCart')->willReturnCallback(
            function (string $eventName, mixed $cart, array $parameters, ?CartValueInterface $value = null): Event {
                $this->assembled[] = [$eventName, $parameters];
                $this->values[] = $value;

                return new Event($eventName, true, $parameters);
            }
        );

        return $assembler;
    }
}
