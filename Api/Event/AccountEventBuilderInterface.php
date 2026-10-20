<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Event;

use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;

/**
 * Builds the event of a shopper's account action on the storefront (signing in, signing up)
 */
interface AccountEventBuilderInterface
{
    /**
     * Build the event
     *
     * @param int $storeId The store view the event belongs to
     * @return EventInterface|null Null when the event is disabled for the store view
     */
    public function build(int $storeId): ?EventInterface;
}
