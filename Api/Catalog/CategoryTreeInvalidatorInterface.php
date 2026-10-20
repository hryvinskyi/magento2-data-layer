<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Catalog;

/**
 * Drops every stored category tree, so the next read of any store's tree rebuilds it from the catalogue
 */
interface CategoryTreeInvalidatorInterface
{
    /**
     * Drop the stored trees of every store, in this process and in the shared cache
     *
     * @return void
     */
    public function invalidate(): void;
}
