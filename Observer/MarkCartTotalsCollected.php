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
use Magento\Quote\Api\Data\CartInterface;

/**
 * Forgets the re-priced lines of a cart once its totals have run: they carry their calculated figures again
 */
class MarkCartTotalsCollected implements ObserverInterface
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
        $cart = $observer->getEvent()->getData('quote');
        if ($cart instanceof CartInterface) {
            $this->repricedLines->markTotalsCollected($cart);
        }
    }
}
