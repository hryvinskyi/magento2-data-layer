<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Config;

/**
 * The order amount a purchase's `value` starts from
 */
enum ValueBasis: string
{
    /**
     * The order's grand total (tax and shipping included; the tax can be deducted by a separate setting)
     */
    case GrandTotal = 'grand_total';

    /**
     * The order's subtotal
     */
    case Subtotal = 'subtotal';
}
