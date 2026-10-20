<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Checkout;

use Hryvinskyi\DataLayer\Api\Checkout\PaymentInfoLedgerInterface;
use Hryvinskyi\DataLayer\Api\Session\VisitorSessionInterface;

/**
 * Keeps the cart and payment method the payment step was last reported for in the data layer's session namespace
 *
 * Only the last one is kept: choosing another method reports the step again, and coming back to the first one after
 * that reports it once more, since each is a choice the shopper made.
 */
class PaymentInfoLedger implements PaymentInfoLedgerInterface
{
    private const KEY = 'last_payment_info';

    /**
     * @param VisitorSessionInterface $session
     */
    public function __construct(private readonly VisitorSessionInterface $session)
    {
    }

    /**
     * @inheritDoc
     */
    public function wasReported(int $cartId, string $methodCode): bool
    {
        return $this->session->get(self::KEY) === $this->key($cartId, $methodCode);
    }

    /**
     * @inheritDoc
     */
    public function markReported(int $cartId, string $methodCode): void
    {
        $this->session->set(self::KEY, $this->key($cartId, $methodCode));
    }

    /**
     * Get the stored key of a cart and payment method
     *
     * @param int $cartId
     * @param string $methodCode
     * @return string
     */
    private function key(int $cartId, string $methodCode): string
    {
        return $cartId . ':' . $methodCode;
    }
}
