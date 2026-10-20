<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration\Checkout;

use Hryvinskyi\DataLayer\Test\Integration\ResolvesServices;
use Hryvinskyi\DataLayer\Test\Integration\TakesQueuedEvents;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Api\GuestCartItemRepositoryInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use PHPUnit\Framework\TestCase;

/**
 * Placing an order through the save-payment-and-place-order call reports the payment step exactly once, for a
 * customer's cart and for a guest's: that call saves the payment information through the same service method the
 * payment step alone uses, so the step is reported by the same interception. The carts hold a virtual product, so
 * placing them needs no carrier.
 *
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class PaymentStepOnPlaceOrderTest extends TestCase
{
    use ResolvesServices;
    use TakesQueuedEvents;

    private const EVENT = 'add_payment_info';

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->takeQueuedEvents();
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->service(CheckoutSession::class)->clearQuote();
    }

    /**
     * A customer's cart placed in one call reports one payment step
     *
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @magentoConfigFixture current_store payment/banktransfer/active 1
     * @return void
     */
    public function testCustomerOrderReportsThePaymentStepOnce(): void
    {
        $cartId = (int) $this->service(CartManagementInterface::class)->createEmptyCartForCustomer(1);
        $this->service(CartItemRepositoryInterface::class)->save($this->cartLine((string) $cartId, 'DL-PAY-C'));
        $this->takeQueuedEvents();

        $orderId = $this->service(PaymentInformationManagementInterface::class)->savePaymentInformationAndPlaceOrder(
            $cartId,
            $this->payment(),
            $this->address('billing')
        );

        self::assertGreaterThan(0, (int) $orderId);
        self::assertSame(1, $this->waitingPaymentSteps());
    }

    /**
     * A guest's cart placed in one call reports one payment step
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @magentoConfigFixture current_store payment/banktransfer/active 1
     * @return void
     */
    public function testGuestOrderReportsThePaymentStepOnce(): void
    {
        $maskedId = (string) $this->service(GuestCartManagementInterface::class)->createEmptyCart();
        $this->service(GuestCartItemRepositoryInterface::class)->save($this->cartLine($maskedId, 'DL-PAY-G'));
        $this->takeQueuedEvents();

        $orderId = $this->service(GuestPaymentInformationManagementInterface::class)
            ->savePaymentInformationAndPlaceOrder(
                $maskedId,
                'guest@example.test',
                $this->payment(),
                $this->address('billing')
            );

        self::assertGreaterThan(0, (int) $orderId);
        self::assertSame(1, $this->waitingPaymentSteps());
    }

    /**
     * Count the payment steps waiting for the browser
     *
     * @return int
     */
    private function waitingPaymentSteps(): int
    {
        $count = 0;
        foreach ($this->takeQueuedEvents() as $event) {
            if (($event['event'] ?? null) === self::EVENT) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * A line of two units of a new sellable product for the given cart
     *
     * @param string $cartId The cart's id, or a guest cart's masked id
     * @param string $sku
     * @return CartItemInterface
     */
    private function cartLine(string $cartId, string $sku): CartItemInterface
    {
        $this->sellableProduct($sku);
        $line = $this->fresh(CartItemInterface::class);
        $line->setQuoteId($cartId);
        $line->setSku($sku);
        $line->setQty(2);

        return $line;
    }

    /**
     * A US address
     *
     * @param string $type
     * @return AddressInterface
     */
    private function address(string $type): AddressInterface
    {
        $address = $this->fresh(AddressInterface::class);
        $address->setFirstname('Test')
            ->setLastname($type)
            ->setStreet(['1 Test Street'])
            ->setCity('Austin')
            ->setRegionId(57)
            ->setPostcode('78701')
            ->setCountryId('US')
            ->setTelephone('5555555')
            ->setEmail('buyer@example.test');

        return $address;
    }

    /**
     * Payment by bank transfer
     *
     * @return PaymentInterface
     */
    private function payment(): PaymentInterface
    {
        $payment = $this->fresh(PaymentInterface::class);
        $payment->setMethod('banktransfer');

        return $payment;
    }

    /**
     * Create an enabled virtual product that can be ordered without stock, so the order needs no shipping
     *
     * @param string $sku
     * @return void
     */
    private function sellableProduct(string $sku): void
    {
        $product = $this->fresh(Product::class);
        $product->setTypeId(Type::TYPE_VIRTUAL)
            ->setAttributeSetId(4)
            ->setSku($sku)
            ->setName('Order step ' . $sku)
            ->setPrice(30.0)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setWebsiteIds([1])
            ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0]);
        $this->service(ProductResource::class)->save($product);
    }
}
