<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Observer;

use Hryvinskyi\DataLayer\Api\Catalog\CategoryTreeInvalidatorInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Drops every stored category tree when a category or a store's root catalog changes
 *
 * The catalogue's own category tag is not enough: an ordinary category save (a rename, an activation toggle, a new
 * position) cleans only the tag of that one category. The observer only invalidates; the next read, in this request
 * or any other, rebuilds the tree.
 */
class CleanCategoryTree implements ObserverInterface
{
    /**
     * @param CategoryTreeInvalidatorInterface $trees
     */
    public function __construct(private readonly CategoryTreeInvalidatorInterface $trees)
    {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $this->trees->invalidate();
    }
}
