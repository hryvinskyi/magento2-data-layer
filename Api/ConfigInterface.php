<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

use Hryvinskyi\DataLayer\Model\Config\AdsValueSource;
use Hryvinskyi\DataLayer\Model\Config\CheckoutStepValueBasis;
use Hryvinskyi\DataLayer\Model\Config\HiddenPriceHandling;
use Hryvinskyi\DataLayer\Model\Config\ImpressionTrigger;
use Hryvinskyi\DataLayer\Model\Config\ValueBasis;

/**
 * The data layer's settings for a store view, one typed method per setting
 *
 * Every method reads the store view scope; a null store id means the current store view. A stored value outside a
 * setting's known values reads as that setting's default.
 */
interface ConfigInterface
{
    /**
     * A Google Tag Manager web container id
     */
    public const CONTAINER_ID_PATTERN = '/^GTM-[A-Z0-9]+$/D';

    /**
     * The host Google Tag Manager is loaded from when no other host is configured; gtag.js always comes from it
     */
    public const DEFAULT_SCRIPT_HOST = 'www.googletagmanager.com';

    /**
     * A host name the script host setting accepts, for feedback while typing
     *
     * Labels of letters, digits and inner hyphens, at most 63 characters each, with at least one dot, at most 253
     * characters without a final dot. Saving checks the host with PHP's own host-name validation, which this pattern
     * agrees with.
     */
    public const SCRIPT_HOST_PATTERN = '/^(?=.{1,253}\.?$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?'
        . '(?:(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+\.?|\.)$/iD';

    /**
     * A Google Analytics 4 web stream's measurement id
     */
    public const MEASUREMENT_ID_PATTERN = '/^G-[A-Z0-9]+$/D';

    /**
     * A Google Ads conversion id, as gtag.js names the account
     */
    public const ADS_CONVERSION_ID_PATTERN = '/^AW-\d+$/D';

    /**
     * A Google Ads conversion label: the part after the slash of a conversion's `send_to`
     */
    public const ADS_CONVERSION_LABEL_PATTERN = '/^[A-Za-z0-9_-]+$/D';

    /**
     * Setting: whether catalogue items report prices including tax (`1`) or excluding it (`0`)
     */
    public const XML_PATH_CATALOGUE_PRICE_INCLUDES_TAX = 'hryvinskyi_datalayer/item/catalogue_price_includes_tax';

    /**
     * Setting: whether cart and order line items report prices and discounts including tax (`1`) or excluding it (`0`)
     */
    public const XML_PATH_LINE_PRICE_INCLUDES_TAX = 'hryvinskyi_datalayer/item/line_price_includes_tax';

    /**
     * Setting: a checkout step's value basis, a {@see CheckoutStepValueBasis} value
     */
    public const XML_PATH_CHECKOUT_STEP_VALUE_BASIS = 'hryvinskyi_datalayer/checkout/step_value_basis';

    /**
     * Whether the data layer is on for the store view
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool;

    /**
     * Get the Google Tag Manager container id
     *
     * @param int|null $storeId
     * @return string|null Null when none is set or the stored value is not a container id
     */
    public function getContainerId(?int $storeId = null): ?string;

    /**
     * Get the host Google Tag Manager's scripts are loaded from
     *
     * @param int|null $storeId
     * @return string A host name without scheme or path; Google's own host when none is set
     */
    public function getScriptHost(?int $storeId = null): string;

    /**
     * Get the names of the events the store view does not report
     *
     * @param int|null $storeId
     * @return list<string>
     */
    public function getDisabledEvents(?int $storeId = null): array;

    /**
     * Get the product attribute reported as `item_id`
     *
     * @param int|null $storeId
     * @return string An attribute code, or `entity_id` for the product id; `sku` when none is set
     */
    public function getIdentifierAttribute(?int $storeId = null): string;

    /**
     * Get the product attribute reported as `item_brand`
     *
     * @param int|null $storeId
     * @return string|null An attribute code; null when no brand is reported
     */
    public function getBrandAttribute(?int $storeId = null): ?string;

    /**
     * Get what happens to an ecommerce event when a product's price is hidden from the viewer
     *
     * @param int|null $storeId
     * @return HiddenPriceHandling
     */
    public function getHiddenPriceHandling(?int $storeId = null): HiddenPriceHandling;

    /**
     * Whether a catalogue item's price is reported including tax
     *
     * A catalogue item is a product as listed or viewed (product pages, product lists, promotions' products, a product
     * added to a wish list), not a line of a cart or an order.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCataloguePriceIncludingTax(?int $storeId = null): bool;

    /**
     * Whether a cart or order line's price and discount are reported including tax
     *
     * Applies to every item built from a cart, quote or order line, including a cart line whose totals have not run
     * yet and therefore reports its product's catalogue price.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isLinePriceIncludingTax(?int $storeId = null): bool;

    /**
     * Get what the `value` of a checkout step (`add_shipping_info`, `add_payment_info`) is
     *
     * @param int|null $storeId
     * @return CheckoutStepValueBasis
     */
    public function getCheckoutStepValueBasis(?int $storeId = null): CheckoutStepValueBasis;

    /**
     * Get the order amount a purchase's `value` starts from
     *
     * @param int|null $storeId
     * @return ValueBasis
     */
    public function getPurchaseValueBasis(?int $storeId = null): ValueBasis;

    /**
     * Whether the tax is deducted from a purchase value based on the grand total
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isPurchaseTaxDeducted(?int $storeId = null): bool;

    /**
     * Whether a purchase reports its `tax`
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isPurchaseTaxIncluded(?int $storeId = null): bool;

    /**
     * Whether a purchase reports its `shipping`
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isPurchaseShippingIncluded(?int $storeId = null): bool;

    /**
     * Get when a product list reports its impression
     *
     * @param int|null $storeId
     * @return ImpressionTrigger
     */
    public function getImpressionTrigger(?int $storeId = null): ImpressionTrigger;

    /**
     * Get the stored event routing rows, as saved
     *
     * Rows are read as stored, without checking that the event is known or the channel exists; a row that is not a
     * pair of strings is left out.
     *
     * @param int|null $storeId
     * @return list<array{event: string, channel: string}>
     */
    public function getChannelRouting(?int $storeId = null): array;

    /**
     * Get the Google Analytics 4 measurement id gtag.js reports to
     *
     * @param int|null $storeId
     * @return string|null Null when none is set or the stored value is not a measurement id
     */
    public function getGtagMeasurementId(?int $storeId = null): ?string;

    /**
     * Whether the store view reports a Google Ads conversion for each order
     *
     * Only the switch: a conversion also needs a conversion id and label.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAdsConversionEnabled(?int $storeId = null): bool;

    /**
     * Get the Google Ads conversion id
     *
     * @param int|null $storeId
     * @return string|null `AW-` and digits; null when none is set or the stored value is not one
     */
    public function getAdsConversionId(?int $storeId = null): ?string;

    /**
     * Get the Google Ads conversion label
     *
     * @param int|null $storeId
     * @return string|null Null when none is set or the stored value cannot follow the id in `send_to`
     */
    public function getAdsConversionLabel(?int $storeId = null): ?string;

    /**
     * Get where a Google Ads conversion's value comes from
     *
     * @param int|null $storeId
     * @return AdsValueSource
     */
    public function getAdsValueSource(?int $storeId = null): AdsValueSource;

    /**
     * Get the fixed value of a Google Ads conversion
     *
     * @param int|null $storeId
     * @return float|null Null when none is set or the stored value is not a number of zero or more
     */
    public function getAdsFixedValue(?int $storeId = null): ?float;
}
