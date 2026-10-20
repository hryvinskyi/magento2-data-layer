<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\HiddenPriceDecision;
use Hryvinskyi\DataLayer\Api\HiddenPricePolicyInterface;
use Hryvinskyi\DataLayer\Model\Config\HiddenPriceHandling;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Hryvinskyi\DataLayerApi\Api\PriceVisibilityInterface;

/**
 * Asks price visibility about every product of an event, except those of order lines, and applies the store's
 * hidden-price setting when one is hidden
 *
 * Browsing and cart events only: order events always carry their money and never ask.
 *
 * An order line's price is never hidden: the buyer was charged it, and order-level events are also built in a
 * payment notification, a scheduled job or the admin, where price visibility would judge the request's (signed-out)
 * viewer instead of the buyer and drop the revenue.
 */
class HiddenPricePolicy implements HiddenPricePolicyInterface
{
    /**
     * @param PriceVisibilityInterface $priceVisibility
     * @param ConfigInterface $config
     */
    public function __construct(
        private readonly PriceVisibilityInterface $priceVisibility,
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function decide(array $subjects, ?int $storeId = null): HiddenPriceDecision
    {
        foreach ($subjects as $subject) {
            if (!$this->isPriceVisible($subject)) {
                return match ($this->config->getHiddenPriceHandling($storeId)) {
                    HiddenPriceHandling::OmitPrice => HiddenPriceDecision::OmitMoney,
                    HiddenPriceHandling::SkipEvent => HiddenPriceDecision::SkipEvent,
                };
            }
        }

        return HiddenPriceDecision::AllVisible;
    }

    /**
     * @inheritDoc
     */
    public function isPriceVisible(ItemSubjectInterface $subject): bool
    {
        return $subject->getOrderItem() !== null || $this->priceVisibility->isPriceVisible($subject->getProduct());
    }
}
