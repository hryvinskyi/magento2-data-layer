<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\ItemField;

use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;

/**
 * Reads what the cart or order line of an item subject says about its price, its discount and its coupon
 *
 * Amounts are in the currency of the cart or the order. A subject without a line has none of them.
 */
interface LineFiguresInterface
{
    /**
     * Get the price of one unit of the line
     *
     * @param ItemSubjectInterface $subject
     * @param bool $includingTax
     * @return float|null Null when the subject has no line, or its cart line's totals have not run since an addition
     *     created or raised it
     */
    public function unitPrice(ItemSubjectInterface $subject, bool $includingTax): ?float;

    /**
     * Get the discount on one unit of the line
     *
     * @param ItemSubjectInterface $subject
     * @param bool $includingTax
     * @return float|null Null when the subject has no line, or its cart line's totals have not been collected yet;
     *     zero when the line has no discount
     */
    public function unitDiscount(ItemSubjectInterface $subject, bool $includingTax): ?float;

    /**
     * Get the coupon code of the cart or order the line belongs to
     *
     * @param ItemSubjectInterface $subject
     * @return string|null Null when the subject has no line, its cart or order cannot be resolved, or no coupon was
     *     applied
     */
    public function couponCode(ItemSubjectInterface $subject): ?string;
}
