<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Config;

/**
 * When a product list reports its impression
 */
enum ImpressionTrigger: string
{
    /**
     * As soon as the page has loaded
     */
    case Load = 'load';

    /**
     * When the list first enters the viewport
     */
    case Visible = 'visible';
}
