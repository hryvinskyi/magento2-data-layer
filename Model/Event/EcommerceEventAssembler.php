<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event;

use Hryvinskyi\DataLayer\Api\Cart\CartEventNamingInterface;
use Hryvinskyi\DataLayer\Api\Event\CartValueInterface;
use Hryvinskyi\DataLayer\Api\Event\EcommerceEventAssemblerInterface;
use Hryvinskyi\DataLayer\Api\Event\ItemsValueInterface;
use Hryvinskyi\DataLayer\Api\HiddenPriceDecision;
use Hryvinskyi\DataLayer\Api\HiddenPricePolicyInterface;
use Hryvinskyi\DataLayerApi\Api\CartTrackingPolicyInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Hryvinskyi\DataLayerApi\Api\ItemBuilderInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Assembles the ecommerce events built from a cart line, a whole cart or a catalogue product
 *
 * A cart event is asked for under a shopping cart's name and reported under the name the cart's kind gives it (a
 * shopping cart keeps it); a cart whose kind reports no such event makes none, and nothing else is done for it.
 * The item of a cart line is the product the line was added as (a configurable line's parent); when the line has
 * exactly one child line, that child's product is the chosen variant (a bundle's lines name none). A line whose product
 * is not loaded is left out. A cart's currency is the one it is priced in; a cart that has not been saved yet has
 * none, and then (as for a catalogue product) the store view's current currency is reported. An event's `value`
 * follows one rule, taken from its items as reported: Σ (price − discount) × quantity, on the items' own tax basis;
 * an event about a whole cart whose caller gives another cart value (a checkout step) reports that value instead.
 */
class EcommerceEventAssembler implements EcommerceEventAssemblerInterface
{
    /**
     * @param EventAvailabilityInterface $availability
     * @param CartTrackingPolicyInterface $cartTracking
     * @param HiddenPricePolicyInterface $hiddenPricePolicy
     * @param ItemBuilderInterface $itemBuilder
     * @param ItemSubjectInterfaceFactory $subjectFactory
     * @param EventInterfaceFactory $eventFactory
     * @param StoreManagerInterface $storeManager
     * @param ItemsValueInterface $itemsValue
     * @param CartEventNamingInterface $cartEventNaming
     */
    public function __construct(
        private readonly EventAvailabilityInterface $availability,
        private readonly CartTrackingPolicyInterface $cartTracking,
        private readonly HiddenPricePolicyInterface $hiddenPricePolicy,
        private readonly ItemBuilderInterface $itemBuilder,
        private readonly ItemSubjectInterfaceFactory $subjectFactory,
        private readonly EventInterfaceFactory $eventFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ItemsValueInterface $itemsValue,
        private readonly CartEventNamingInterface $cartEventNaming
    ) {
    }

    /**
     * @inheritDoc
     */
    public function forCartLine(string $eventName, CartItemInterface $line, float $quantity): ?EventInterface
    {
        if ($quantity <= 0.0 || !$line instanceof AbstractItem) {
            return null;
        }
        $cart = $line->getQuote();
        $storeId = (int) $cart->getStoreId();
        $reportedName = $this->reportedName($eventName, $cart, $storeId);
        if ($reportedName === null) {
            return null;
        }

        $subject = $this->lineSubject($line, $quantity, $storeId);
        if ($subject === null) {
            return null;
        }

        return $this->assemble($reportedName, [$subject], $storeId, $this->cartCurrency($cart, $storeId));
    }

    /**
     * @inheritDoc
     */
    public function forCart(
        string $eventName,
        CartInterface $cart,
        array $parameters = [],
        ?CartValueInterface $value = null
    ): ?EventInterface {
        $storeId = (int) $cart->getStoreId();
        if (!$cart instanceof Quote) {
            return null;
        }
        $reportedName = $this->reportedName($eventName, $cart, $storeId);
        if ($reportedName === null) {
            return null;
        }

        $subjects = [];
        foreach ($cart->getAllVisibleItems() as $line) {
            $subject = $this->lineSubject($line, $this->number($line->getQty()), $storeId);
            if ($subject !== null) {
                $subjects[] = $subject;
            }
        }
        if ($subjects === []) {
            return null;
        }

        $coupon = $this->text($cart->getData('coupon_code'));

        return $this->assemble(
            $reportedName,
            $subjects,
            $storeId,
            $this->cartCurrency($cart, $storeId),
            ($coupon !== '' ? ['coupon' => $coupon] : []) + $parameters,
            $cart,
            $value
        );
    }

    /**
     * @inheritDoc
     */
    public function forProduct(
        string $eventName,
        ProductInterface $product,
        float $quantity,
        int $storeId
    ): ?EventInterface {
        if ($quantity <= 0.0 || !$this->availability->isEnabled($eventName, $storeId)) {
            return null;
        }

        $subject = $this->subjectFactory->create([
            'product' => $product,
            'storeId' => $storeId,
            'quantity' => $quantity,
        ]);

        return $this->assemble($eventName, [$subject], $storeId, $this->storeCurrency($storeId));
    }

    /**
     * Get the name a cart reports an event under, when the event is reported for it at all
     *
     * @param string $cartEvent The shopping cart's event
     * @param CartInterface $cart
     * @param int $storeId
     * @return string|null Null when the cart's kind reports no such event, the event is disabled for the store view
     *     or the cart is not tracked
     */
    private function reportedName(string $cartEvent, CartInterface $cart, int $storeId): ?string
    {
        $name = $this->cartEventNaming->eventFor($cartEvent, $cart);
        if ($name === null
            || !$this->availability->isEnabled($name, $storeId)
            || !$this->cartTracking->isTracked($cart)
        ) {
            return null;
        }

        return $name;
    }

    /**
     * Build the items and the event, applying the hidden-price rule
     *
     * @param string $eventName
     * @param list<ItemSubjectInterface> $subjects
     * @param int $storeId
     * @param string $currency Empty when none is known
     * @param array<string,string> $parameters Further parameters, after `currency` and `value`
     * @param CartInterface|null $cart The cart a whole-cart event is about
     * @param CartValueInterface|null $cartValue What that cart is worth; null for the items' value
     * @return EventInterface|null Null when the event is skipped for a hidden price or no item was built
     */
    private function assemble(
        string $eventName,
        array $subjects,
        int $storeId,
        string $currency,
        array $parameters = [],
        ?CartInterface $cart = null,
        ?CartValueInterface $cartValue = null
    ): ?EventInterface {
        $decision = $this->hiddenPricePolicy->decide($subjects, $storeId);
        if ($decision === HiddenPriceDecision::SkipEvent) {
            return null;
        }

        $items = $this->itemBuilder->build($subjects, $eventName);
        if ($items === []) {
            return null;
        }

        $eventParameters = $currency !== '' ? ['currency' => $currency] : [];
        if ($decision === HiddenPriceDecision::AllVisible) {
            $eventParameters['value'] = $cart !== null && $cartValue !== null
                ? $cartValue->valueOf($cart, $items)
                : $this->itemsValue->value($items);
        }

        return $this->eventFactory->create([
            'name' => $eventName,
            'ecommerce' => true,
            'parameters' => $eventParameters + $parameters,
            'items' => $items,
        ]);
    }

    /**
     * Get the subject of a cart line
     *
     * @param AbstractItem $line
     * @param float $quantity
     * @param int $storeId
     * @return ItemSubjectInterface|null Null for a line whose product is not loaded
     */
    private function lineSubject(AbstractItem $line, float $quantity, int $storeId): ?ItemSubjectInterface
    {
        $product = $this->loadedProduct($line->getProduct());
        if ($product === null) {
            return null;
        }

        return $this->subjectFactory->create([
            'product' => $product,
            'storeId' => $storeId,
            'quantity' => $quantity,
            'childProduct' => $this->onlyChildProduct($line),
            'quoteItem' => $line,
        ]);
    }

    /**
     * Get the product of a line's only child line (a configurable line's chosen variant)
     *
     * @param AbstractItem $line
     * @return ProductInterface|null Null for a line without children or with several (a bundle's selections)
     */
    private function onlyChildProduct(AbstractItem $line): ?ProductInterface
    {
        $children = array_values($line->getChildren());

        return count($children) === 1 ? $this->loadedProduct($children[0]->getProduct()) : null;
    }

    /**
     * Take a line's product as it comes back
     *
     * A line is documented as always having its product, but one whose product was never loaded returns null.
     *
     * @param mixed $product What the line returned as its product
     * @return ProductInterface|null
     */
    private function loadedProduct(mixed $product): ?ProductInterface
    {
        return $product instanceof ProductInterface ? $product : null;
    }

    /**
     * Get the currency a cart is priced in, or the store view's when the cart has none yet
     *
     * @param Quote $cart
     * @param int $storeId
     * @return string Empty when none is known
     */
    private function cartCurrency(Quote $cart, int $storeId): string
    {
        $currency = $this->text($cart->getData('quote_currency_code'));

        return $currency !== '' ? $currency : $this->storeCurrency($storeId);
    }

    /**
     * Get the store view's current currency
     *
     * @param int $storeId
     * @return string Empty when none is known
     */
    private function storeCurrency(int $storeId): string
    {
        $store = $this->storeManager->getStore($storeId);

        return $store instanceof Store ? $this->text($store->getCurrentCurrencyCode()) : '';
    }

    /**
     * Read a stored amount
     *
     * @param mixed $value
     * @return float Zero when nothing numeric is stored
     */
    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * Read stored text
     *
     * @param mixed $value
     * @return string Empty when no text is stored
     */
    private function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
