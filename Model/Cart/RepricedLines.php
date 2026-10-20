<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Cart;

use Hryvinskyi\DataLayer\Api\Cart\RepricedLinesInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;

/**
 * Keeps the re-priced lines of this request by the line object itself, without holding it alive
 *
 * One shared instance per request; a line object that is gone is forgotten with it.
 */
class RepricedLines implements RepricedLinesInterface
{
    /**
     * @var \WeakMap<CartItemInterface, true>
     */
    private \WeakMap $repriced;

    /**
     * Start with no re-priced line
     */
    public function __construct()
    {
        $this->repriced = new \WeakMap();
    }

    /**
     * @inheritDoc
     */
    public function markRepriced(CartItemInterface $line): void
    {
        $this->repriced[$line] = true;
    }

    /**
     * @inheritDoc
     */
    public function markTotalsCollected(CartInterface $cart): void
    {
        if (!$cart instanceof Quote) {
            return;
        }
        foreach ($cart->getAllItems() as $line) {
            unset($this->repriced[$line]);
        }
    }

    /**
     * @inheritDoc
     */
    public function isAwaitingTotals(CartItemInterface $line): bool
    {
        return isset($this->repriced[$line]);
    }
}
