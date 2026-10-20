<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

/**
 * What an event builder does with an ecommerce event whose items include a product whose price the viewer may not see
 */
enum HiddenPriceDecision
{
    /**
     * Every price is visible: the event is reported with its money parameters
     */
    case AllVisible;

    /**
     * The event is reported without its money parameters (`value`, `tax`, `shipping`, and each item's `price`
     * and `discount`)
     */
    case OmitMoney;

    /**
     * The event is not reported at all
     */
    case SkipEvent;
}
