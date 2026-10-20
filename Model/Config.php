<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\Config\DraftSettingReaderInterface;
use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Model\Config\AdsValueSource;
use Hryvinskyi\DataLayer\Model\Config\CheckoutStepValueBasis;
use Hryvinskyi\DataLayer\Model\Config\HiddenPriceHandling;
use Hryvinskyi\DataLayer\Model\Config\ImpressionTrigger;
use Hryvinskyi\DataLayer\Model\Config\ValueBasis;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads the data layer's settings at store view scope
 *
 * A path drafted for the running request answers from the draft before the stored configuration, so every setting
 * below reads unsaved values the same way it reads saved ones.
 */
class Config implements ConfigInterface
{
    private const XML_PATH_ENABLED = 'hryvinskyi_datalayer/general/enabled';
    private const XML_PATH_CONTAINER_ID = 'hryvinskyi_datalayer/gtm/container_id';
    private const XML_PATH_SCRIPT_HOST = 'hryvinskyi_datalayer/gtm/script_host';
    private const XML_PATH_DISABLED_EVENTS = 'hryvinskyi_datalayer/events/disabled';
    private const XML_PATH_IDENTIFIER_ATTRIBUTE = 'hryvinskyi_datalayer/item/identifier_attribute';
    private const XML_PATH_BRAND_ATTRIBUTE = 'hryvinskyi_datalayer/item/brand_attribute';
    private const XML_PATH_HIDDEN_PRICE_HANDLING = 'hryvinskyi_datalayer/item/hidden_price_handling';
    private const XML_PATH_VALUE_BASIS = 'hryvinskyi_datalayer/purchase/value_basis';
    private const XML_PATH_DEDUCT_TAX = 'hryvinskyi_datalayer/purchase/deduct_tax';
    private const XML_PATH_INCLUDE_TAX = 'hryvinskyi_datalayer/purchase/include_tax';
    private const XML_PATH_INCLUDE_SHIPPING = 'hryvinskyi_datalayer/purchase/include_shipping';
    private const XML_PATH_IMPRESSION_TRIGGER = 'hryvinskyi_datalayer/lists/impression_trigger';
    private const XML_PATH_CHANNEL_ROUTING = 'hryvinskyi_datalayer/channels/routing';
    private const XML_PATH_MEASUREMENT_ID = 'hryvinskyi_datalayer/gtag/measurement_id';
    private const XML_PATH_ADS_ENABLED = 'hryvinskyi_datalayer/ads/enabled';
    private const XML_PATH_ADS_CONVERSION_ID = 'hryvinskyi_datalayer/ads/conversion_id';
    private const XML_PATH_ADS_CONVERSION_LABEL = 'hryvinskyi_datalayer/ads/conversion_label';
    private const XML_PATH_ADS_VALUE_SOURCE = 'hryvinskyi_datalayer/ads/value_source';
    private const XML_PATH_ADS_FIXED_VALUE = 'hryvinskyi_datalayer/ads/fixed_value';

    private const DEFAULT_IDENTIFIER_ATTRIBUTE = 'sku';

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param SerializerInterface $serializer Reads the routing rows the way the admin saved them
     * @param DraftSettingReaderInterface $draftSettings Settings drafted for the running request, read first
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly SerializerInterface $serializer,
        private readonly DraftSettingReaderInterface $draftSettings
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_ENABLED, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getContainerId(?int $storeId = null): ?string
    {
        $containerId = $this->text(self::XML_PATH_CONTAINER_ID, $storeId);

        return preg_match(self::CONTAINER_ID_PATTERN, $containerId) === 1 ? $containerId : null;
    }

    /**
     * @inheritDoc
     */
    public function getScriptHost(?int $storeId = null): string
    {
        $host = $this->text(self::XML_PATH_SCRIPT_HOST, $storeId);

        return $host !== '' ? $host : self::DEFAULT_SCRIPT_HOST;
    }

    /**
     * @inheritDoc
     */
    public function getDisabledEvents(?int $storeId = null): array
    {
        $names = array_map('trim', explode(',', $this->text(self::XML_PATH_DISABLED_EVENTS, $storeId)));

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
    }

    /**
     * @inheritDoc
     */
    public function getIdentifierAttribute(?int $storeId = null): string
    {
        $attributeCode = $this->text(self::XML_PATH_IDENTIFIER_ATTRIBUTE, $storeId);

        return $attributeCode !== '' ? $attributeCode : self::DEFAULT_IDENTIFIER_ATTRIBUTE;
    }

    /**
     * @inheritDoc
     */
    public function getBrandAttribute(?int $storeId = null): ?string
    {
        $attributeCode = $this->text(self::XML_PATH_BRAND_ATTRIBUTE, $storeId);

        return $attributeCode !== '' ? $attributeCode : null;
    }

    /**
     * @inheritDoc
     */
    public function getHiddenPriceHandling(?int $storeId = null): HiddenPriceHandling
    {
        return HiddenPriceHandling::tryFrom($this->text(self::XML_PATH_HIDDEN_PRICE_HANDLING, $storeId))
            ?? HiddenPriceHandling::OmitPrice;
    }

    /**
     * @inheritDoc
     */
    public function isCataloguePriceIncludingTax(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_CATALOGUE_PRICE_INCLUDES_TAX, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function isLinePriceIncludingTax(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_LINE_PRICE_INCLUDES_TAX, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getCheckoutStepValueBasis(?int $storeId = null): CheckoutStepValueBasis
    {
        return CheckoutStepValueBasis::tryFrom($this->text(self::XML_PATH_CHECKOUT_STEP_VALUE_BASIS, $storeId))
            ?? CheckoutStepValueBasis::Items;
    }

    /**
     * @inheritDoc
     */
    public function getPurchaseValueBasis(?int $storeId = null): ValueBasis
    {
        return ValueBasis::tryFrom($this->text(self::XML_PATH_VALUE_BASIS, $storeId)) ?? ValueBasis::GrandTotal;
    }

    /**
     * @inheritDoc
     */
    public function isPurchaseTaxDeducted(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_DEDUCT_TAX, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function isPurchaseTaxIncluded(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_INCLUDE_TAX, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function isPurchaseShippingIncluded(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_INCLUDE_SHIPPING, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getImpressionTrigger(?int $storeId = null): ImpressionTrigger
    {
        return ImpressionTrigger::tryFrom($this->text(self::XML_PATH_IMPRESSION_TRIGGER, $storeId))
            ?? ImpressionTrigger::Load;
    }

    /**
     * @inheritDoc
     */
    public function getChannelRouting(?int $storeId = null): array
    {
        $stored = $this->text(self::XML_PATH_CHANNEL_ROUTING, $storeId);
        if ($stored === '') {
            return [];
        }

        try {
            $rows = $this->serializer->unserialize($stored);
        } catch (\InvalidArgumentException) {
            return [];
        }

        if (!is_array($rows)) {
            return [];
        }

        $routing = [];
        foreach ($rows as $row) {
            if (is_array($row) && is_string($row['event'] ?? null) && is_string($row['channel'] ?? null)) {
                $routing[] = ['event' => $row['event'], 'channel' => $row['channel']];
            }
        }

        return $routing;
    }

    /**
     * @inheritDoc
     */
    public function getGtagMeasurementId(?int $storeId = null): ?string
    {
        return $this->matching(self::XML_PATH_MEASUREMENT_ID, self::MEASUREMENT_ID_PATTERN, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function isAdsConversionEnabled(?int $storeId = null): bool
    {
        return $this->flag(self::XML_PATH_ADS_ENABLED, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getAdsConversionId(?int $storeId = null): ?string
    {
        return $this->matching(self::XML_PATH_ADS_CONVERSION_ID, self::ADS_CONVERSION_ID_PATTERN, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getAdsConversionLabel(?int $storeId = null): ?string
    {
        return $this->matching(self::XML_PATH_ADS_CONVERSION_LABEL, self::ADS_CONVERSION_LABEL_PATTERN, $storeId);
    }

    /**
     * @inheritDoc
     */
    public function getAdsValueSource(?int $storeId = null): AdsValueSource
    {
        return AdsValueSource::tryFrom($this->text(self::XML_PATH_ADS_VALUE_SOURCE, $storeId))
            ?? AdsValueSource::Purchase;
    }

    /**
     * @inheritDoc
     */
    public function getAdsFixedValue(?int $storeId = null): ?float
    {
        $value = $this->text(self::XML_PATH_ADS_FIXED_VALUE, $storeId);
        if (!is_numeric($value)) {
            return null;
        }
        $amount = (float) $value;

        return is_finite($amount) && $amount >= 0.0 ? $amount : null;
    }

    /**
     * Read a setting that must match a pattern
     *
     * @param string $path
     * @param string $pattern
     * @param int|null $storeId
     * @return string|null The trimmed value; null when it is empty or does not match
     */
    private function matching(string $path, string $pattern, ?int $storeId): ?string
    {
        $value = $this->text($path, $storeId);

        return preg_match($pattern, $value) === 1 ? $value : null;
    }

    /**
     * Read a yes/no setting
     *
     * A drafted flag is on only when drafted as "1" or "true"; "0", an empty value and anything else read as off.
     *
     * @param string $path
     * @param int|null $storeId
     * @return bool
     */
    private function flag(string $path, ?int $storeId): bool
    {
        if ($this->draftSettings->has($path)) {
            return in_array(trim($this->draftSettings->value($path)), ['1', 'true'], true);
        }

        return $this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Read a setting as trimmed text; a missing or non-text value reads as empty
     *
     * @param string $path
     * @param int|null $storeId
     * @return string
     */
    private function text(string $path, ?int $storeId): string
    {
        if ($this->draftSettings->has($path)) {
            return trim($this->draftSettings->value($path));
        }
        $value = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
