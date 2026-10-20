<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayerApi\Api\Data\VisitorContextInterface;
use Hryvinskyi\DataLayerApi\Api\VisitorContextProviderInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Knows no visitor: what a store without server-side delivery has
 *
 * A module that captures visitor identity replaces it.
 */
class NoVisitorContext implements VisitorContextProviderInterface
{
    /**
     * @inheritDoc
     */
    public function current(): ?VisitorContextInterface
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function forOrder(OrderInterface $order): ?VisitorContextInterface
    {
        return null;
    }
}
