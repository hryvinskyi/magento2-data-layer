<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Observer;

use Hryvinskyi\DataLayer\Api\Event\ProductQuantityEventBuilderInterface;
use Hryvinskyi\DataLayerApi\Api\EventDispatcherInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Wishlist\Model\Item as WishlistItem;
use Psr\Log\LoggerInterface;

/**
 * Reports a product the shopper put on a wish list, with the quantity the wish list now holds for it (one when the
 * wish list item does not say)
 *
 * A failure is logged and never reaches the shopper's action.
 */
class ReportWishlistAddition implements ObserverInterface
{
    /**
     * @param ProductQuantityEventBuilderInterface $builder
     * @param EventDispatcherInterface $dispatcher
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ProductQuantityEventBuilderInterface $builder,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getData('product');
        if (!$product instanceof ProductInterface) {
            return;
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $event = $this->builder->build($product, $this->quantity($observer->getEvent()->getData('item')), $storeId);
            if ($event !== null) {
                $this->dispatcher->dispatch($event, $storeId);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('A GA4 wish list addition could not be reported.', [
                'product_id' => $product->getId(),
                'exception' => $exception,
            ]);
        }
    }

    /**
     * Get the quantity the wish list holds for the product
     *
     * @param mixed $item The wish list item the addition created or raised
     * @return float
     */
    private function quantity(mixed $item): float
    {
        $quantity = $item instanceof WishlistItem ? $item->getData('qty') : null;

        return is_numeric($quantity) && (float) $quantity > 0.0 ? (float) $quantity : 1.0;
    }
}
