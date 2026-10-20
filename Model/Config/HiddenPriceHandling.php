<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Config;

/**
 * What happens to an ecommerce event when the price of one of its products is hidden from the viewer
 */
enum HiddenPriceHandling: string
{
    /**
     * The event is reported without its money parameters (`value`, `tax`, `shipping`, `price`, `discount`)
     */
    case OmitPrice = 'omit_price';

    /**
     * The event is not reported at all
     */
    case SkipEvent = 'skip_event';
}
