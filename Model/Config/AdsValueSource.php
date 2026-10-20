<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Config;

/**
 * Where the value of a Google Ads conversion comes from
 */
enum AdsValueSource: string
{
    /**
     * The order's purchase value, on the store's purchase settings, in the order's currency
     */
    case Purchase = 'purchase';

    /**
     * One fixed amount for every order, in the order's currency
     */
    case Fixed = 'fixed';
}
