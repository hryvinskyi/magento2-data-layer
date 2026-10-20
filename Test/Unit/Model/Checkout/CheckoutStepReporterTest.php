<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Checkout;

use Hryvinskyi\DataLayer\Api\Event\CheckoutStepEventBuilderInterface;
use Hryvinskyi\DataLayer\Model\Checkout\CheckoutStepReporter;
use Hryvinskyi\DataLayer\Model\Checkout\PaymentInfoLedger;
use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Test\Unit\Model\RecordingLogger;
use Hryvinskyi\DataLayer\Test\Unit\Model\Session\FakeVisitorSession;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\EventDispatcherInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;

/**
 * Checkout steps are dispatched for the cart's store view; the payment step once per cart and payment method; a
 * failure is logged and never escapes
 */
class CheckoutStepReporterTest extends TestCase
{
    /**
     * @var list<array{string, int}>
     */
    private array $dispatched = [];

    /**
     * @var RecordingLogger
     */
    private RecordingLogger $logger;

    /**
     * @var FakeVisitorSession
     */
    private FakeVisitorSession $session;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
        $this->session = new FakeVisitorSession();
    }

    /**
     * The shipping step is dispatched for the cart's store view each time it is saved
     *
     * @return void
     */
    public function testShippingStepIsDispatched(): void
    {
        $reporter = $this->reporter();

        $reporter->reportShippingInformation(7);
        $reporter->reportShippingInformation(7);

        self::assertSame([['add_shipping_info', 2], ['add_shipping_info', 2]], $this->dispatched);
    }

    /**
     * The same cart and payment method are reported once; another method is reported again
     *
     * @return void
     */
    public function testPaymentStepIsReportedOncePerCartAndMethod(): void
    {
        $reporter = $this->reporter();

        $reporter->reportPaymentInformation(7, 'checkmo');
        $reporter->reportPaymentInformation(7, 'checkmo');
        $reporter->reportPaymentInformation(7, 'banktransfer');
        $reporter->reportPaymentInformation(7, 'checkmo');

        self::assertSame(
            [['add_payment_info', 2], ['add_payment_info', 2], ['add_payment_info', 2]],
            $this->dispatched
        );
    }

    /**
     * A payment step that builds no event (disabled, untracked cart) is not remembered as reported
     *
     * @return void
     */
    public function testPaymentStepWithoutEventIsNotRemembered(): void
    {
        $nothing = $this->createMock(CheckoutStepEventBuilderInterface::class);
        $nothing->method('build')->willReturn(null);
        $reporter = $this->reporter(paymentInfo: $nothing);

        $reporter->reportPaymentInformation(7, 'checkmo');

        self::assertSame([], $this->dispatched);
        self::assertSame([], $this->session->values);
    }

    /**
     * A guest's cart is found by the id its checkout knows it by; an unknown id is logged
     *
     * @return void
     */
    public function testGuestCartIsResolved(): void
    {
        $reporter = $this->reporter();

        $reporter->reportGuestPaymentInformation('masked-7', 'checkmo');
        $reporter->reportGuestPaymentInformation('unknown', 'checkmo');

        self::assertSame([['add_payment_info', 2]], $this->dispatched);
        self::assertCount(1, $this->logger->records);
    }

    /**
     * A cart that cannot be read is logged and the checkout goes on
     *
     * @return void
     */
    public function testFailureIsLogged(): void
    {
        $carts = $this->createMock(CartRepositoryInterface::class);
        $carts->method('get')->willThrowException(new NoSuchEntityException(__('No cart')));
        $reporter = $this->reporter(carts: $carts);

        $reporter->reportShippingInformation(7);
        $reporter->reportPaymentInformation(7, 'checkmo');

        self::assertSame([], $this->dispatched);
        self::assertCount(2, $this->logger->records);
        self::assertSame(['cart_id' => 7, 'method' => 'checkmo'], array_diff_key(
            $this->logger->records[1]['context'],
            ['exception' => true]
        ));
    }

    /**
     * The reporter over a cart 7 of store view 2
     *
     * @param CartRepositoryInterface|null $carts
     * @param CheckoutStepEventBuilderInterface|null $paymentInfo
     * @return CheckoutStepReporter
     */
    private function reporter(
        ?CartRepositoryInterface $carts = null,
        ?CheckoutStepEventBuilderInterface $paymentInfo = null
    ): CheckoutStepReporter {
        if ($carts === null) {
            $cart = $this->createMock(Quote::class);
            $cart->method('getStoreId')->willReturn(2);
            $carts = $this->createMock(CartRepositoryInterface::class);
            $carts->method('get')->with(7)->willReturn($cart);
        }
        $masked = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $masked->method('execute')->willReturnCallback(static function (string $id): int {
            if ($id !== 'masked-7') {
                throw new NoSuchEntityException(__('No cart'));
            }

            return 7;
        });
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (EventInterface $event, int $storeId): void {
            $this->dispatched[] = [$event->getName(), $storeId];
        });

        return new CheckoutStepReporter(
            $carts,
            $masked,
            $this->builder('add_shipping_info'),
            $paymentInfo ?? $this->builder('add_payment_info'),
            new PaymentInfoLedger($this->session),
            $dispatcher,
            $this->logger
        );
    }

    /**
     * A step builder that always builds its event
     *
     * @param string $eventName
     * @return CheckoutStepEventBuilderInterface
     */
    private function builder(string $eventName): CheckoutStepEventBuilderInterface
    {
        $builder = $this->createMock(CheckoutStepEventBuilderInterface::class);
        $builder->method('build')->willReturnCallback(static fn (): Event => new Event($eventName, true));

        return $builder;
    }
}
