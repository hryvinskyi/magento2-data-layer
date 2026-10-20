<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event\ConversionValue;

use Hryvinskyi\DataLayer\Api\Data\ConversionValueInterface;
use Hryvinskyi\DataLayer\Api\Data\ConversionValueInterfaceFactory;
use Hryvinskyi\DataLayer\Api\Event\ConversionValueSourceInterface;
use Hryvinskyi\DataLayer\Api\Event\TransactionMoneyInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Values a conversion at the order's purchase value, in the order's currency
 *
 * The amount is the one `purchase` reports for the order: the same totals, on the same store settings (value basis and
 * tax deduction), so the conversion and the purchase never disagree.
 */
class PurchaseValue implements ConversionValueSourceInterface
{
    /**
     * @param TransactionMoneyInterface $transactionMoney
     * @param ConversionValueInterfaceFactory $valueFactory
     */
    public function __construct(
        private readonly TransactionMoneyInterface $transactionMoney,
        private readonly ConversionValueInterfaceFactory $valueFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function valueOf(OrderInterface $order): ?ConversionValueInterface
    {
        $currency = trim((string) $order->getOrderCurrencyCode());
        if ($currency === '') {
            return null;
        }
        $money = $this->transactionMoney->forOrder($order);

        return $this->valueFactory->create(['amount' => max(0.0, $money['value'] ?? 0.0), 'currency' => $currency]);
    }
}
