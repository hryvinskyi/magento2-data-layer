<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\ItemField;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\ItemField\LineFiguresInterface;
use Hryvinskyi\DataLayerApi\Api\ItemFieldProviderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Fills what only an item from a cart or order line has: `discount`, `coupon` and `affiliation`
 *
 * `discount` is the line's discount per unit on the store's line tax basis, rounded to two decimals, reported only when
 * there is one; `coupon` the cart's or order's coupon code; `affiliation` the store's frontend name.
 */
class LineProvider implements ItemFieldProviderInterface
{
    /**
     * @param ConfigInterface $config
     * @param LineFiguresInterface $lineFigures
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LineFiguresInterface $lineFigures,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function provide(array $subjects, string $eventName): array
    {
        $includingTax = [];
        $affiliations = [];
        $fields = [];
        foreach ($subjects as $key => $subject) {
            if ($subject->getQuoteItem() === null && $subject->getOrderItem() === null) {
                continue;
            }
            $storeId = $subject->getStoreId();
            $includingTax[$storeId] ??= $this->config->isLinePriceIncludingTax($storeId);
            $affiliations[$storeId] ??= $this->storeName($storeId);

            $itemFields = [];
            $discount = round($this->lineFigures->unitDiscount($subject, $includingTax[$storeId]) ?? 0.0, 2);
            if ($discount > 0) {
                $itemFields['discount'] = $discount;
            }
            $coupon = $this->lineFigures->couponCode($subject);
            if ($coupon !== null) {
                $itemFields['coupon'] = $coupon;
            }
            if ($affiliations[$storeId] !== '') {
                $itemFields['affiliation'] = $affiliations[$storeId];
            }
            if ($itemFields !== []) {
                $fields[$key] = $itemFields;
            }
        }

        return $fields;
    }

    /**
     * Get the name the store view shows shoppers
     *
     * @param int $storeId
     * @return string Empty for a store view that does not exist
     */
    private function storeName(int $storeId): string
    {
        try {
            $store = $this->storeManager->getStore($storeId);
        } catch (NoSuchEntityException) {
            return '';
        }

        return trim($store instanceof Store ? $store->getFrontendName() : (string) $store->getName());
    }
}
