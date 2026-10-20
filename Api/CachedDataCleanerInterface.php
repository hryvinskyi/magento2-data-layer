<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

/**
 * Drops everything the data layer cached, for when what it was built from changes as a whole (its settings)
 *
 * Cached list data and category trees depend on the settings (identifier and brand attributes, disabled events,
 * hidden-price handling), so after a change they are rebuilt on their next read rather than served as they were.
 */
interface CachedDataCleanerInterface
{
    /**
     * Drop every entry the data layer keeps in the application cache, and the trees this process holds
     *
     * @return void
     */
    public function clean(): void;
}
