<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Plugin\Checkout;

use Hryvinskyi\DataLayer\Api\Cart\CartQuantityChangeReporterInterface;
use Hryvinskyi\DataLayer\Api\Cart\RepricedLinesInterface;
use Magento\Checkout\Model\Cart;
use Psr\Log\LoggerInterface;

/**
 * Reports the quantity changes of a cart update (the cart page, the mini cart): each line's quantity is read before
 * and after the update, an increase reported as units added and a decrease as units removed
 *
 * The rule needs the state before and after one call, so the plugin wraps the update. It keeps the update's contract:
 * it returns the update's result and lets every exception through, including the one the update throws after applying
 * the valid quantities (those are still reported). A line the update removed is not reported here: the cart announces
 * its removal, and that is reported as a whole. A failure to report is logged and never reaches the update.
 *
 * The update changes quantities without running the cart's totals, so a changed line still carries the price, tier
 * price and discount calculated for its old quantity. Each changed line is marked as awaiting totals before it is
 * reported: its price falls back to the catalogue price for the new quantity and no discount is reported, until the
 * cart's totals run again.
 */
class CartQuantityChangePlugin
{
    private const NOISE = 0.0001;

    /**
     * @param CartQuantityChangeReporterInterface $reporter
     * @param RepricedLinesInterface $repricedLines
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CartQuantityChangeReporterInterface $reporter,
        private readonly RepricedLinesInterface $repricedLines,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Read the lines' quantities, run the update, report what changed
     *
     * @param Cart $subject
     * @param callable $proceed
     * @param mixed $data The update, keyed by cart line id
     * @return mixed The update's own result
     * @throws \Throwable Whatever the update throws
     */
    public function aroundUpdateItems(Cart $subject, callable $proceed, mixed $data): mixed
    {
        $before = $this->quantities($subject, $data);
        try {
            return $proceed($data);
        } finally {
            $this->reportChanges($subject, $before);
        }
    }

    /**
     * Read the current quantity of every cart line the update names
     *
     * @param Cart $cart
     * @param mixed $data
     * @return array<int, float> Quantity by cart line id
     */
    private function quantities(Cart $cart, mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        try {
            $quote = $cart->getQuote();
            $quantities = [];
            foreach (array_keys($data) as $lineId) {
                $line = $quote->getItemById($lineId);
                if ($line !== false) {
                    $quantities[(int) $lineId] = (float) $line->getQty();
                }
            }

            return $quantities;
        } catch (\Throwable $exception) {
            $this->logger->error('The cart quantities could not be read before an update.', [
                'exception' => $exception,
            ]);

            return [];
        }
    }

    /**
     * Report the change of every line still in the cart, each changed line marked as awaiting totals first
     *
     * @param Cart $cart
     * @param array<int,float> $before Quantity by cart line id before the update
     * @return void
     */
    private function reportChanges(Cart $cart, array $before): void
    {
        if ($before === []) {
            return;
        }

        try {
            $quote = $cart->getQuote();
            foreach ($before as $lineId => $quantity) {
                $line = $quote->getItemById($lineId);
                if ($line === false || $line->isDeleted()) {
                    continue;
                }
                $change = (float) $line->getQty() - $quantity;
                if (abs($change) < self::NOISE) {
                    continue;
                }
                $this->repricedLines->markRepriced($line);
                $this->reporter->report($line, $change);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('The quantity changes of a cart update could not be reported.', [
                'exception' => $exception,
            ]);
        }
    }
}
