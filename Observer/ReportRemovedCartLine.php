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
 * Reports a line removed from the cart, with every unit it held
 *
 * The cart announces each removed line, including the lines a quantity update sets to zero. A child line (of a
 * configurable or bundle product) is reported through its parent.
 */
class ReportRemovedCartLine implements ObserverInterface
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
        $line = $observer->getEvent()->getData('quote_item');
        if (!$line instanceof CartLine || $this->isPresent($line->getParentItem())) {
            return;
        }

        $this->reporter->report($line, -(float) $line->getQty());
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
