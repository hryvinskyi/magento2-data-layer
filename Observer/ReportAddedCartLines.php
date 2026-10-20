<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Observer;

use Hryvinskyi\DataLayer\Api\Cart\CartQuantityChangeReporterInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item as CartLine;

/**
 * Reports the units a product added to the cart put on each cart line
 *
 * The cart hands over every line one addition created or raised. A line with a parent line (a configurable or bundle
 * product's child) is reported through its parent; every other line (a simple product, a configurable or bundle
 * parent, each line of a grouped product) is reported with the units the addition put on it, which the line records
 * when its quantity is raised. The cart reports every addition this way, whichever action made it (the product page,
 * related products added with it, a wish list, a reorder).
 *
 * Re-saving a line with new options (editing a cart line on the product page) adds the product again with the line's
 * count reset: the line records its whole new quantity as added and zero as its quantity before. Only the real change
 * is reported then, against the quantity the line was loaded with: an unchanged re-save reports nothing, a raise its
 * extra units, a lowering the units removed. A reset line whose loaded quantity is unknown reports nothing.
 */
class ReportAddedCartLines implements ObserverInterface
{
    /**
     * @param CartQuantityChangeReporterInterface $reporter
     */
    public function __construct(private readonly CartQuantityChangeReporterInterface $reporter)
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
            if ($line instanceof CartLine && !$this->isPresent($line->getParentItem())) {
                $this->reporter->report($line, $this->addedUnits($line));
            }
        }
    }

    /**
     * Get the units the addition put on a line, negative when a re-save with a reset count lowered it
     *
     * @param CartLine $line
     * @return float Zero when the line did not record them
     */
    private function addedUnits(CartLine $line): float
    {
        if ($this->wasRecounted($line)) {
            $loaded = $line->getOrigData('qty');

            return is_numeric($loaded) ? (float) $line->getQty() - (float) $loaded : 0.0;
        }

        $units = $line->getData('qty_to_add');

        return is_numeric($units) ? (float) $units : 0.0;
    }

    /**
     * Whether the addition reset the count of a line that was already in the cart
     *
     * A line already in the cart always held units before an addition; it records zero as its quantity before only
     * when the addition reset its count first.
     *
     * @param CartLine $line
     * @return bool
     */
    private function wasRecounted(CartLine $line): bool
    {
        $before = $line->getData('previous_qty');

        return $line->getId() !== null && is_numeric($before) && (float) $before <= 0.0;
    }

    /**
     * Whether a line's parent line is set
     *
     * A line is documented as always having a parent, but a line without one returns null, so the answer is taken as
     * it comes back.
     *
     * @param mixed $parent What the line returned as its parent line
     * @return bool
     */
    private function isPresent(mixed $parent): bool
    {
        return $parent !== null;
    }
}
