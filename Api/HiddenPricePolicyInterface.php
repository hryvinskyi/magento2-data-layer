<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;

/**
 * Decides what happens to an ecommerce event when the price of one of its products is hidden from the viewer
 *
 * The rule governs browsing and cart events only (lists, product views, wish list, cart and checkout steps). Order
 * events (a purchase, a refund, an Ads conversion) are business records and always carry their money: their builders
 * do not ask for a decision. An item that comes from an order line also always counts as visible: the buyer was
 * charged that price, and order-level events are built outside the buyer's own request too (a payment notification,
 * a scheduled job, the admin), where the request's viewer is not the buyer.
 */
interface HiddenPricePolicyInterface
{
    /**
     * Decide for the subjects of one event
     *
     * @param list<ItemSubjectInterface> $subjects
     * @param int|null $storeId The store view whose hidden-price setting applies; null for the current one
     * @return HiddenPriceDecision All visible when every price is visible, otherwise what the store's setting says
     */
    public function decide(array $subjects, ?int $storeId = null): HiddenPriceDecision;

    /**
     * Whether one subject's price may be reported to the viewer
     *
     * @param ItemSubjectInterface $subject
     * @return bool Always true for an item that comes from an order line
     */
    public function isPriceVisible(ItemSubjectInterface $subject): bool;
}
