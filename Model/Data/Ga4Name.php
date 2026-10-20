<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Data;

/**
 * The shape GA4 accepts for an event name and for a parameter name
 *
 * A name starts with a lower-case letter and continues with lower-case letters, digits and underscores, forty
 * characters at most. The pattern ends with `$` under the `D` modifier, so a trailing line break is refused too.
 */
class Ga4Name
{
    public const PATTERN = '/^[a-z][a-z0-9_]{0,39}$/D';
}
