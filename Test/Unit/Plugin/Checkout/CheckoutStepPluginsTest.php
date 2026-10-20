<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Plugin\Checkout;

use Hryvinskyi\DataLayer\Api\Checkout\CheckoutStepReporterInterface;
use Hryvinskyi\DataLayer\Plugin\Checkout\GuestPaymentInformationPlugin;
use Hryvinskyi\DataLayer\Plugin\Checkout\PaymentInformationPlugin;
use Hryvinskyi\DataLayer\Plugin\Checkout\ShippingInformationPlugin;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use PHPUnit\Framework\TestCase;

/**
 * The checkout step plugins report a save that happened (a `false` from the rate limiter reports nothing) and hand
 * back the service's result untouched
 */
class CheckoutStepPluginsTest extends TestCase
{
    /**
     * @var list<array<int|string>>
     */
    private array $reported = [];

    /**
     * The shipping step is reported with the cart's id
     *
     * @return void
     */
    public function testShippingStep(): void
    {
        $result = new \stdClass();

        self::assertSame($result, (new ShippingInformationPlugin($this->reporter()))->afterSaveAddressInformation(
            $this->createMock(ShippingInformationManagementInterface::class),
            $result,
            '12'
        ));
        self::assertSame([['shipping', 12]], $this->reported);
    }

    /**
     * A customer's payment step is reported only when the save happened
     *
     * @return void
     */
    public function testCustomerPaymentStep(): void
    {
        $plugin = new PaymentInformationPlugin($this->reporter());
        $service = $this->createMock(PaymentInformationManagementInterface::class);

        self::assertFalse($plugin->afterSavePaymentInformation($service, false, 12, $this->method('checkmo')));
        self::assertTrue($plugin->afterSavePaymentInformation($service, true, 12, $this->method('checkmo')));

        self::assertSame([['payment', 12, 'checkmo']], $this->reported);
    }

    /**
     * A guest's payment step is reported with the id the guest's checkout knows the cart by, only when saved
     *
     * @return void
     */
    public function testGuestPaymentStep(): void
    {
        $plugin = new GuestPaymentInformationPlugin($this->reporter());
        $service = $this->createMock(GuestPaymentInformationManagementInterface::class);

        self::assertFalse($plugin->afterSavePaymentInformation($service, false, 'abc', 'a@b.c', $this->method('free')));
        self::assertTrue($plugin->afterSavePaymentInformation($service, true, 'abc', 'a@b.c', $this->method('free')));

        self::assertSame([['guest-payment', 'abc', 'free']], $this->reported);
    }

    /**
     * A payment method choice
     *
     * @param string $code
     * @return PaymentInterface
     */
    private function method(string $code): PaymentInterface
    {
        $method = $this->createMock(PaymentInterface::class);
        $method->method('getMethod')->willReturn($code);

        return $method;
    }

    /**
     * A reporter that records what it is told
     *
     * @return CheckoutStepReporterInterface
     */
    private function reporter(): CheckoutStepReporterInterface
    {
        $reporter = $this->createMock(CheckoutStepReporterInterface::class);
        $reporter->method('reportShippingInformation')->willReturnCallback(function (int $cartId): void {
            $this->reported[] = ['shipping', $cartId];
        });
        $reporter->method('reportPaymentInformation')->willReturnCallback(
            function (int $cartId, string $method): void {
                $this->reported[] = ['payment', $cartId, $method];
            }
        );
        $reporter->method('reportGuestPaymentInformation')->willReturnCallback(
            function (string $cartId, string $method): void {
                $this->reported[] = ['guest-payment', $cartId, $method];
            }
        );

        return $reporter;
    }
}
