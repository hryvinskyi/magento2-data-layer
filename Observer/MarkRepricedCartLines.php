<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Observer;

use Hryvinskyi\DataLayer\Api\Cart\RepricedLinesInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\CartItemInterface;

/**
 * Remembers every line a product addition created or raised (children included) as re-priced until its cart's totals
 * run
 */
class MarkRepricedCartLines implements ObserverInterface
{
    /**
     * @param RepricedLinesInterface $repricedLines
     */
    public function __construct(private readonly RepricedLinesInterface $repricedLines)
    {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $lines = $observer->getEvent()->getData('items');
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            if ($line instanceof CartItemInterface) {
                $this->repricedLines->markRepriced($line);
            }
        }
    }
}
