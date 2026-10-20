<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event;

use Hryvinskyi\DataLayer\Api\Event\PurchaseEventBuilderInterface;
use Hryvinskyi\DataLayer\Api\Event\OrderItemSubjectsInterface;
use Hryvinskyi\DataLayer\Api\Event\TransactionMoneyInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Hryvinskyi\DataLayerApi\Api\ItemBuilderInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds `purchase` for an order, from the order and its store view's settings alone
 *
 * Parameters: `transaction_id` the order's increment id, `affiliation` its store view's frontend name, `currency`
 * the order's currency, `value`/`tax`/`shipping` on the store's transaction basis, `coupon` the order's coupon
 * code. Items are the order's visible lines. An order is a business record: it always carries its money, whatever
 * the store's setting for prices hidden from a visitor says (that setting governs browsing and cart events), since
 * the buyer was charged it, whoever's request builds the event.
 */
class PurchaseEventBuilder implements PurchaseEventBuilderInterface
{
    public const EVENT_NAME = 'purchase';

    /**
     * @param EventAvailabilityInterface $availability
     * @param OrderItemSubjectsInterface $orderItemSubjects
     * @param ItemBuilderInterface $itemBuilder
     * @param TransactionMoneyInterface $transactionMoney
     * @param EventInterfaceFactory $eventFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly EventAvailabilityInterface $availability,
        private readonly OrderItemSubjectsInterface $orderItemSubjects,
        private readonly ItemBuilderInterface $itemBuilder,
        private readonly TransactionMoneyInterface $transactionMoney,
        private readonly EventInterfaceFactory $eventFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function build(OrderInterface $order): ?EventInterface
    {
        $storeId = (int) $order->getStoreId();
        if (!$this->availability->isEnabled(self::EVENT_NAME, $storeId)) {
            return null;
        }

        $subjects = $this->orderItemSubjects->subjects($order->getItems(), $storeId);
        if ($subjects === []) {
            return null;
        }
        $items = $this->itemBuilder->build($subjects, self::EVENT_NAME);
        if ($items === []) {
            return null;
        }

        return $this->eventFactory->create([
            'name' => self::EVENT_NAME,
            'ecommerce' => true,
            'parameters' => $this->parameters($order, $storeId),
            'items' => $items,
        ]);
    }

    /**
     * Get the order's event-level parameters, money included
     *
     * @param OrderInterface $order
     * @param int $storeId
     * @return array<string, string|float>
     */
    private function parameters(OrderInterface $order, int $storeId): array
    {
        $texts = [
            'transaction_id' => trim((string) $order->getIncrementId()),
            'affiliation' => $this->storeName($storeId),
            'currency' => trim((string) $order->getOrderCurrencyCode()),
        ];
        $parameters = [];
        foreach ($texts as $name => $text) {
            if ($text !== '') {
                $parameters[$name] = $text;
            }
        }
        $money = $this->transactionMoney->forOrder($order);
        $parameters += $money;
        $coupon = trim((string) $order->getCouponCode());
        if ($coupon !== '') {
            $parameters['coupon'] = $coupon;
        }

        return $parameters;
    }

    /**
     * Get the name a store view shows shoppers
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
