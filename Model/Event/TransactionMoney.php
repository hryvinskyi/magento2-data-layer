<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\Event\TransactionMoneyInterface;
use Hryvinskyi\DataLayer\Model\Config\ValueBasis;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Applies the store's purchase settings to a transaction's totals
 *
 * The value basis, the tax deduction and the tax and shipping toggles are the same for every transaction event, so an
 * order and a refund of it are reported on one basis. Either basis yields revenue net of discounts: the grand total
 * already is, the subtotal has the discount of the document's items taken off. A discount of shipping is not taken
 * off the subtotal, which holds no shipping.
 */
class TransactionMoney implements TransactionMoneyInterface
{
    /**
     * @param ConfigInterface $config
     */
    public function __construct(private readonly ConfigInterface $config)
    {
    }

    /**
     * @inheritDoc
     */
    public function parameters(
        float $grandTotal,
        float $subtotal,
        float $tax,
        float $shipping,
        int $storeId,
        float $discount = 0.0
    ): array {
        $value = match ($this->config->getPurchaseValueBasis($storeId)) {
            ValueBasis::GrandTotal => $this->config->isPurchaseTaxDeducted($storeId) ? $grandTotal - $tax : $grandTotal,
            ValueBasis::Subtotal => $subtotal - abs($discount),
        };

        $parameters = ['value' => round(max(0.0, $value), 2)];
        if ($this->config->isPurchaseTaxIncluded($storeId)) {
            $parameters['tax'] = round($tax, 2);
        }
        if ($this->config->isPurchaseShippingIncluded($storeId)) {
            $parameters['shipping'] = round($shipping, 2);
        }

        return $parameters;
    }

    /**
     * @inheritDoc
     */
    public function forOrder(OrderInterface $order): array
    {
        return $this->parameters(
            (float) $order->getGrandTotal(),
            (float) $order->getSubtotal(),
            (float) $order->getTaxAmount(),
            (float) $order->getShippingAmount(),
            (int) $order->getStoreId(),
            $this->itemsPart($order->getDiscountAmount(), $order->getShippingDiscountAmount())
                - $this->itemsPart(
                    $order->getDiscountTaxCompensationAmount(),
                    $order->getShippingDiscountTaxCompensationAmount()
                )
        );
    }

    /**
     * Get the part of a document's amount that concerns its items: the whole amount less the shipping's share
     *
     * @param mixed $whole The document's amount, stored with either sign
     * @param mixed $shipping The shipping's share of it, stored with either sign
     * @return float Never negative
     */
    private function itemsPart(mixed $whole, mixed $shipping): float
    {
        return max(0.0, abs($this->number($whole)) - abs($this->number($shipping)));
    }

    /**
     * Read a stored amount
     *
     * @param mixed $value
     * @return float Zero when nothing numeric is stored
     */
    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
