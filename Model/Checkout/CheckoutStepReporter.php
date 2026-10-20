<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Checkout;

use Hryvinskyi\DataLayer\Api\Checkout\CheckoutStepReporterInterface;
use Hryvinskyi\DataLayer\Api\Checkout\PaymentInfoLedgerInterface;
use Hryvinskyi\DataLayer\Api\Event\CheckoutStepEventBuilderInterface;
use Hryvinskyi\DataLayerApi\Api\EventDispatcherInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Psr\Log\LoggerInterface;

/**
 * Reports the checkout steps a shopper completed, through the event dispatcher
 *
 * The cart is read through the cart repository, which hands back the instance the checkout service has just saved.
 * The payment step is reported once per cart and payment method: saving the payment information runs again when the
 * order is placed, and a payment method may save it twice before that. A failure is logged with the cart it concerns
 * and never reaches the shopper's checkout.
 */
class CheckoutStepReporter implements CheckoutStepReporterInterface
{
    /**
     * @param CartRepositoryInterface $carts
     * @param MaskedQuoteIdToQuoteIdInterface $maskedCartIds
     * @param CheckoutStepEventBuilderInterface $shippingInfo Builds the shipping step's event
     * @param CheckoutStepEventBuilderInterface $paymentInfo Builds the payment step's event
     * @param PaymentInfoLedgerInterface $paymentLedger
     * @param EventDispatcherInterface $dispatcher
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CartRepositoryInterface $carts,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedCartIds,
        private readonly CheckoutStepEventBuilderInterface $shippingInfo,
        private readonly CheckoutStepEventBuilderInterface $paymentInfo,
        private readonly PaymentInfoLedgerInterface $paymentLedger,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function reportShippingInformation(int $cartId): void
    {
        try {
            $cart = $this->carts->get($cartId);
            $event = $this->shippingInfo->build($cart);
            if ($event !== null) {
                $this->dispatcher->dispatch($event, (int) $cart->getStoreId());
            }
        } catch (\Throwable $exception) {
            $this->logFailure('shipping information', ['cart_id' => $cartId], $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function reportPaymentInformation(int $cartId, string $methodCode): void
    {
        try {
            if ($this->paymentLedger->wasReported($cartId, $methodCode)) {
                return;
            }
            $cart = $this->carts->get($cartId);
            $event = $this->paymentInfo->build($cart);
            if ($event === null) {
                return;
            }
            $this->dispatcher->dispatch($event, (int) $cart->getStoreId());
            $this->paymentLedger->markReported($cartId, $methodCode);
        } catch (\Throwable $exception) {
            $this->logFailure('payment information', ['cart_id' => $cartId, 'method' => $methodCode], $exception);
        }
    }

    /**
     * @inheritDoc
     */
    public function reportGuestPaymentInformation(string $maskedCartId, string $methodCode): void
    {
        try {
            $cartId = $this->maskedCartIds->execute($maskedCartId);
        } catch (\Throwable $exception) {
            $this->logFailure('payment information', ['method' => $methodCode], $exception);
            return;
        }

        $this->reportPaymentInformation($cartId, $methodCode);
    }

    /**
     * Log a step that could not be reported
     *
     * @param string $step
     * @param array<string,int|string> $context
     * @param \Throwable $exception
     * @return void
     */
    private function logFailure(string $step, array $context, \Throwable $exception): void
    {
        $this->logger->error(
            sprintf('The GA4 checkout step "%s" could not be reported.', $step),
            $context + ['exception' => $exception]
        );
    }
}
