<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event\ConversionValue;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\Data\ConversionValueInterface;
use Hryvinskyi\DataLayer\Api\Data\ConversionValueInterfaceFactory;
use Hryvinskyi\DataLayer\Api\Event\ConversionValueSourceInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Values every conversion at the order store view's fixed amount, in the order's currency
 */
class FixedValue implements ConversionValueSourceInterface
{
    /**
     * @param ConfigInterface $config
     * @param ConversionValueInterfaceFactory $valueFactory
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ConversionValueInterfaceFactory $valueFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function valueOf(OrderInterface $order): ?ConversionValueInterface
    {
        $amount = $this->config->getAdsFixedValue((int) $order->getStoreId());
        $currency = trim((string) $order->getOrderCurrencyCode());
        if ($amount === null || $currency === '') {
            return null;
        }

        return $this->valueFactory->create(['amount' => $amount, 'currency' => $currency]);
    }
}
