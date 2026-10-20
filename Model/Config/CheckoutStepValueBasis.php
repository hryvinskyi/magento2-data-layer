<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Config;

/**
 * What a checkout step's (`add_shipping_info`, `add_payment_info`) `value` is
 */
enum CheckoutStepValueBasis: string
{
    /**
     * The items' value, the rule of every cart event: Σ (price − discount) × quantity, on the line tax basis, without
     * shipping
     */
    case Items = 'items';

    /**
     * The cart's grand total: tax and shipping included, discounts taken off
     */
    case GrandTotal = 'grand_total';
}
