<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Cart;

use Hryvinskyi\DataLayer\Api\Cart\CartQuantityChangeReporterInterface;
use Hryvinskyi\DataLayer\Api\Event\CartLineEventBuilderInterface;
use Hryvinskyi\DataLayerApi\Api\EventDispatcherInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Psr\Log\LoggerInterface;

/**
 * Reports a cart line's quantity change through the event dispatcher: units added as `add_to_cart`, units removed as
 * `remove_from_cart`, both for the units that changed, each under the name the cart's kind gives it
 *
 * A change smaller than a ten-thousandth of a unit is rounding noise and reports nothing. A failure is logged with the
 * line it concerns and never reaches the shopper's cart action.
 */
class CartQuantityChangeReporter implements CartQuantityChangeReporterInterface
{
    private const NOISE = 0.0001;

    /**
     * @param CartLineEventBuilderInterface $addToCart Builds the event of units added
     * @param CartLineEventBuilderInterface $removeFromCart Builds the event of units removed
     * @param EventDispatcherInterface $dispatcher
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CartLineEventBuilderInterface $addToCart,
        private readonly CartLineEventBuilderInterface $removeFromCart,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function report(CartItemInterface $line, float $change): void
    {
        if (abs($change) < self::NOISE || !$line instanceof AbstractItem) {
            return;
        }

        try {
            $event = $change > 0
                ? $this->addToCart->build($line, $change)
                : $this->removeFromCart->build($line, -$change);
            if ($event !== null) {
                $this->dispatcher->dispatch($event, (int) $line->getQuote()->getStoreId());
            }
        } catch (\Throwable $exception) {
            $this->logger->error('A GA4 cart quantity change could not be reported.', [
                'cart_item_id' => $line->getId(),
                'product_id' => $line->getData('product_id'),
                'change' => $change,
                'exception' => $exception,
            ]);
        }
    }
}
