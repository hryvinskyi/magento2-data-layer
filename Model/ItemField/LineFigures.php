<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\ItemField;

use Hryvinskyi\DataLayer\Api\Cart\RepricedLinesInterface;
use Hryvinskyi\DataLayer\Api\ItemField\LineFiguresInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Model\Config as TaxConfig;

/**
 * Reads a cart line's or an order line's unit price, unit discount and coupon in the cart's or the order's currency
 *
 * Excluding tax, a cart line's unit price is the price it is calculated with (a custom price when one was set); an
 * order line's is the price it was ordered at. Including tax, each line's own price including tax. The discount
 * excluding tax is the line's discount less the part of it that was tax. Including tax it depends on what the store
 * view applies discounts to (Magento's "Apply Discount On Prices"): applied to prices including tax, the line's
 * discount already includes tax; applied to prices excluding tax, it does not, and the discount excluding tax is
 * raised by the line's own tax rate. Either is divided by the line's own quantity.
 *
 * A cart line counts as calculated only once its cart's totals ran for it: a line an addition created or raised in
 * this request carries the product's raw final price (no tier price for its quantity, no tax basis, no discount)
 * until then, and has neither a price nor a discount here. A line whose cart or order cannot be resolved has no
 * coupon; its other figures are still read.
 */
class LineFigures implements LineFiguresInterface
{
    /**
     * @param RepricedLinesInterface $repricedLines
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly RepricedLinesInterface $repricedLines,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function unitPrice(ItemSubjectInterface $subject, bool $includingTax): ?float
    {
        $orderItem = $subject->getOrderItem();
        if ($orderItem !== null) {
            return $this->number($includingTax ? $orderItem->getPriceInclTax() : $orderItem->getPrice());
        }

        $cartItem = $subject->getQuoteItem();
        if (!$cartItem instanceof AbstractItem || !$this->isCalculated($cartItem, $includingTax)) {
            return null;
        }

        return $this->number(
            $includingTax ? $cartItem->getData('price_incl_tax') : $cartItem->getCalculationPrice()
        );
    }

    /**
     * @inheritDoc
     */
    public function unitDiscount(ItemSubjectInterface $subject, bool $includingTax): ?float
    {
        $orderItem = $subject->getOrderItem();
        if ($orderItem !== null) {
            $quantity = $this->number($orderItem->getQtyOrdered()) ?? 0.0;
            if ($quantity <= 0.0) {
                return null;
            }

            return $this->discount(
                $this->number($orderItem->getDiscountAmount()) ?? 0.0,
                $this->number($orderItem->getDiscountTaxCompensationAmount()) ?? 0.0,
                $this->number($orderItem->getTaxPercent()) ?? 0.0,
                $includingTax,
                $subject->getStoreId()
            ) / $quantity;
        }

        $cartItem = $subject->getQuoteItem();
        if (!$cartItem instanceof AbstractItem || !$this->isCalculated($cartItem, $includingTax)) {
            return null;
        }
        $quantity = $this->number($cartItem->getQty()) ?? 0.0;
        if ($quantity <= 0.0) {
            return null;
        }

        return $this->discount(
            $this->number($cartItem->getData('discount_amount')) ?? 0.0,
            $this->number($cartItem->getData('discount_tax_compensation_amount')) ?? 0.0,
            $this->number($cartItem->getData('tax_percent')) ?? 0.0,
            $includingTax,
            $subject->getStoreId()
        ) / $quantity;
    }

    /**
     * @inheritDoc
     */
    public function couponCode(ItemSubjectInterface $subject): ?string
    {
        $orderItem = $subject->getOrderItem();
        if ($orderItem !== null) {
            return $orderItem instanceof OrderItem ? $this->orderCoupon($orderItem->getOrder()) : null;
        }

        $cartItem = $subject->getQuoteItem();

        return $cartItem instanceof AbstractItem ? $this->cartCoupon($cartItem->getQuote()) : null;
    }

    /**
     * Whether a cart line's totals were collected on the requested basis
     *
     * A line an addition re-priced is not calculated until its cart's totals ran. Otherwise, excluding tax, a line is
     * calculated once it carries a price (or a custom price); including tax, once it carries its price including tax.
     *
     * @param AbstractItem $cartItem
     * @param bool $includingTax
     * @return bool
     */
    private function isCalculated(AbstractItem $cartItem, bool $includingTax): bool
    {
        if ($cartItem instanceof CartItemInterface && $this->repricedLines->isAwaitingTotals($cartItem)) {
            return false;
        }
        if ($includingTax) {
            return $this->number($cartItem->getData('price_incl_tax')) !== null;
        }

        return $this->number($cartItem->getData('custom_price')) !== null
            || $this->number($cartItem->getData('price')) !== null;
    }

    /**
     * Read the coupon of the order a line belongs to
     *
     * An order line resolves its order lazily and gets none when it has no order id, so the order is taken as it
     * comes back, whatever it is.
     *
     * @param mixed $order What the order line returned as its order
     * @return string|null Null when there is no order or no coupon
     */
    private function orderCoupon(mixed $order): ?string
    {
        return $order instanceof OrderInterface ? $this->code($order->getCouponCode()) : null;
    }

    /**
     * Read the coupon of the cart a line belongs to
     *
     * A cart line that was never attached to its cart has none, so the cart is taken as it comes back, whatever it is.
     *
     * @param mixed $cart What the cart line returned as its cart
     * @return string|null Null when there is no cart or no coupon
     */
    private function cartCoupon(mixed $cart): ?string
    {
        return $cart instanceof Quote ? $this->code($cart->getData('coupon_code')) : null;
    }

    /**
     * Get a line's whole discount on the requested tax basis
     *
     * @param float $discount The discount as applied to the line
     * @param float $taxCompensation The part of that discount that was tax
     * @param float $taxPercent The line's tax rate, in percent
     * @param bool $includingTax
     * @param int $storeId The store view whose discount setting applies
     * @return float
     */
    private function discount(
        float $discount,
        float $taxCompensation,
        float $taxPercent,
        bool $includingTax,
        int $storeId
    ): float {
        if (!$includingTax) {
            return $discount - $taxCompensation;
        }
        if ($this->isDiscountAppliedIncludingTax($storeId)) {
            return $discount;
        }

        return ($discount - $taxCompensation) * (1 + $taxPercent / 100);
    }

    /**
     * Whether the store view applies discounts to prices including tax, so a line's discount already includes tax
     *
     * @param int $storeId
     * @return bool
     */
    private function isDiscountAppliedIncludingTax(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            TaxConfig::CONFIG_XML_PATH_DISCOUNT_TAX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Read a stored amount
     *
     * @param mixed $value
     * @return float|null Null when nothing numeric is stored
     */
    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Read a stored coupon code
     *
     * @param mixed $value
     * @return string|null Null when no code is stored
     */
    private function code(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
