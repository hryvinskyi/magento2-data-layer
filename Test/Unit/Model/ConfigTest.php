<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Model\Config;
use Hryvinskyi\DataLayer\Model\Config\DraftSettings;
use Hryvinskyi\DataLayer\Model\Config\AdsValueSource;
use Hryvinskyi\DataLayer\Model\Config\CheckoutStepValueBasis;
use Hryvinskyi\DataLayer\Model\Config\HiddenPriceHandling;
use Hryvinskyi\DataLayer\Model\Config\ImpressionTrigger;
use Hryvinskyi\DataLayer\Model\Config\ValueBasis;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

/**
 * Settings come back typed; values outside a setting's known values read as its default
 */
class ConfigTest extends TestCase
{
    /**
     * Stored known values come back as their enum cases, flags as booleans, for the store asked
     *
     * @return void
     */
    public function testStoredValuesComeBackTyped(): void
    {
        $scopeConfig = new FakeScopeConfig([
            'hryvinskyi_datalayer/general/enabled' => '1',
            'hryvinskyi_datalayer/gtm/container_id' => ' GTM-AB12CD ',
            'hryvinskyi_datalayer/gtm/script_host' => 'sgtm.example.com',
            'hryvinskyi_datalayer/item/identifier_attribute' => 'mpn',
            'hryvinskyi_datalayer/item/brand_attribute' => 'manufacturer',
            'hryvinskyi_datalayer/item/hidden_price_handling' => 'skip_event',
            'hryvinskyi_datalayer/item/catalogue_price_includes_tax' => '1',
            'hryvinskyi_datalayer/item/line_price_includes_tax' => '0',
            'hryvinskyi_datalayer/checkout/step_value_basis' => 'grand_total',
            'hryvinskyi_datalayer/purchase/value_basis' => 'subtotal',
            'hryvinskyi_datalayer/purchase/deduct_tax' => '1',
            'hryvinskyi_datalayer/purchase/include_tax' => '0',
            'hryvinskyi_datalayer/purchase/include_shipping' => '1',
            'hryvinskyi_datalayer/lists/impression_trigger' => 'visible',
        ]);
        $config = new Config($scopeConfig, new Json(), new DraftSettings());

        self::assertTrue($config->isEnabled(5));
        self::assertSame('GTM-AB12CD', $config->getContainerId(5));
        self::assertSame('sgtm.example.com', $config->getScriptHost(5));
        self::assertSame('mpn', $config->getIdentifierAttribute(5));
        self::assertSame('manufacturer', $config->getBrandAttribute(5));
        self::assertSame(HiddenPriceHandling::SkipEvent, $config->getHiddenPriceHandling(5));
        self::assertTrue($config->isCataloguePriceIncludingTax(5));
        self::assertFalse($config->isLinePriceIncludingTax(5));
        self::assertSame(CheckoutStepValueBasis::GrandTotal, $config->getCheckoutStepValueBasis(5));
        self::assertSame(ValueBasis::Subtotal, $config->getPurchaseValueBasis(5));
        self::assertTrue($config->isPurchaseTaxDeducted(5));
        self::assertFalse($config->isPurchaseTaxIncluded(5));
        self::assertTrue($config->isPurchaseShippingIncluded(5));
        self::assertSame(ImpressionTrigger::Visible, $config->getImpressionTrigger(5));
        self::assertSame([5], array_values(array_unique($scopeConfig->askedScopes)));
    }

    /**
     * Unknown stored values read as each setting's default
     *
     * @return void
     */
    public function testUnknownValuesFallBackToDefaults(): void
    {
        $config = new Config(
            new FakeScopeConfig([
                'hryvinskyi_datalayer/item/hidden_price_handling' => 'hide_everything',
                'hryvinskyi_datalayer/purchase/value_basis' => 'net',
                'hryvinskyi_datalayer/lists/impression_trigger' => 'scroll',
                'hryvinskyi_datalayer/checkout/step_value_basis' => 'subtotal',
                'hryvinskyi_datalayer/gtm/container_id' => '<script>GTM-AB12</script>',
            ]),
            new Json(),
            new DraftSettings()
        );

        self::assertSame(HiddenPriceHandling::OmitPrice, $config->getHiddenPriceHandling());
        self::assertSame(ValueBasis::GrandTotal, $config->getPurchaseValueBasis());
        self::assertSame(ImpressionTrigger::Load, $config->getImpressionTrigger());
        self::assertSame(CheckoutStepValueBasis::Items, $config->getCheckoutStepValueBasis());
        self::assertNull($config->getContainerId());
    }

    /**
     * Missing values read as each setting's default
     *
     * @return void
     */
    public function testMissingValuesReadAsDefaults(): void
    {
        $config = new Config(new FakeScopeConfig(), new Json(), new DraftSettings());

        self::assertFalse($config->isEnabled());
        self::assertNull($config->getContainerId());
        self::assertSame('www.googletagmanager.com', $config->getScriptHost());
        self::assertSame([], $config->getDisabledEvents());
        self::assertSame('sku', $config->getIdentifierAttribute());
        self::assertNull($config->getBrandAttribute());
        self::assertSame(HiddenPriceHandling::OmitPrice, $config->getHiddenPriceHandling());
        self::assertFalse($config->isCataloguePriceIncludingTax());
        self::assertFalse($config->isLinePriceIncludingTax());
        self::assertSame(CheckoutStepValueBasis::Items, $config->getCheckoutStepValueBasis());
        self::assertSame(ValueBasis::GrandTotal, $config->getPurchaseValueBasis());
        self::assertSame(ImpressionTrigger::Load, $config->getImpressionTrigger());
        self::assertSame([], $config->getChannelRouting());
        self::assertNull($config->getGtagMeasurementId());
        self::assertFalse($config->isAdsConversionEnabled());
        self::assertNull($config->getAdsConversionId());
        self::assertNull($config->getAdsConversionLabel());
        self::assertSame(AdsValueSource::Purchase, $config->getAdsValueSource());
        self::assertNull($config->getAdsFixedValue());
    }

    /**
     * The gtag and Google Ads settings come back trimmed and typed for the store asked
     *
     * @return void
     */
    public function testGtagAndAdsSettingsComeBackTyped(): void
    {
        $scopeConfig = new FakeScopeConfig([
            'hryvinskyi_datalayer/gtag/measurement_id' => ' G-AB12CD34 ',
            'hryvinskyi_datalayer/ads/enabled' => '1',
            'hryvinskyi_datalayer/ads/conversion_id' => 'AW-1234567',
            'hryvinskyi_datalayer/ads/conversion_label' => ' QgmJ_Lu-9 ',
            'hryvinskyi_datalayer/ads/value_source' => 'fixed',
            'hryvinskyi_datalayer/ads/fixed_value' => '12.5',
        ]);
        $config = new Config($scopeConfig, new Json(), new DraftSettings());

        self::assertSame('G-AB12CD34', $config->getGtagMeasurementId(7));
        self::assertTrue($config->isAdsConversionEnabled(7));
        self::assertSame('AW-1234567', $config->getAdsConversionId(7));
        self::assertSame('QgmJ_Lu-9', $config->getAdsConversionLabel(7));
        self::assertSame(AdsValueSource::Fixed, $config->getAdsValueSource(7));
        self::assertSame(12.5, $config->getAdsFixedValue(7));
        self::assertSame([7], array_values(array_unique($scopeConfig->askedScopes)));
    }

    /**
     * An id, label or amount the tags could not use reads as none; an unknown value source as the purchase value
     *
     * @return void
     */
    public function testUnusableGtagAndAdsSettingsReadAsNone(): void
    {
        $config = new Config(
            new FakeScopeConfig([
                'hryvinskyi_datalayer/gtag/measurement_id' => 'UA-1234-1',
                'hryvinskyi_datalayer/ads/conversion_id' => '1234567',
                'hryvinskyi_datalayer/ads/conversion_label' => 'a/b',
                'hryvinskyi_datalayer/ads/value_source' => 'margin',
                'hryvinskyi_datalayer/ads/fixed_value' => '-1',
            ]),
            new Json(),
            new DraftSettings()
        );

        self::assertNull($config->getGtagMeasurementId());
        self::assertNull($config->getAdsConversionId());
        self::assertNull($config->getAdsConversionLabel());
        self::assertSame(AdsValueSource::Purchase, $config->getAdsValueSource());
        self::assertNull($config->getAdsFixedValue());
        foreach (['ten', 'INF', ''] as $amount) {
            $fixed = new Config(
                new FakeScopeConfig(['hryvinskyi_datalayer/ads/fixed_value' => $amount]),
                new Json(),
                new DraftSettings()
            );
            self::assertNull($fixed->getAdsFixedValue(), $amount);
        }
    }

    /**
     * The disabled events are the comma list's names, trimmed, without blanks or repeats
     *
     * @return void
     */
    public function testDisabledEventsAreTheListedNames(): void
    {
        $config = new Config(
            new FakeScopeConfig(['hryvinskyi_datalayer/events/disabled' => 'purchase, login,,purchase ']),
            new Json(),
            new DraftSettings()
        );

        self::assertSame(['purchase', 'login'], $config->getDisabledEvents());
    }

    /**
     * Routing rows are read as saved; rows that are not two strings are left out
     *
     * @return void
     */
    public function testRoutingRowsAreReadAsSaved(): void
    {
        $stored = json_encode([
            '_1' => ['event' => 'purchase', 'channel' => 'server'],
            '_2' => ['event' => 'nonsense', 'channel' => 'nowhere'],
            '_3' => ['event' => 'login'],
            '_4' => 'junk',
            '_5' => ['event' => 5, 'channel' => 'both'],
        ]);
        $config = new Config(
            new FakeScopeConfig(['hryvinskyi_datalayer/channels/routing' => $stored]),
            new Json(),
            new DraftSettings()
        );

        self::assertSame(
            [['event' => 'purchase', 'channel' => 'server'], ['event' => 'nonsense', 'channel' => 'nowhere']],
            $config->getChannelRouting()
        );
    }

    /**
     * Routing that is not JSON, or not a list of rows, reads as no routing
     *
     * @return void
     */
    public function testUnreadableRoutingReadsAsNone(): void
    {
        foreach (['{not json', '"text"'] as $stored) {
            $config = new Config(
                new FakeScopeConfig(['hryvinskyi_datalayer/channels/routing' => $stored]),
                new Json(),
                new DraftSettings()
            );
            self::assertSame([], $config->getChannelRouting(), $stored);
        }
    }

    /**
     * The catalogue and line tax bases are read independently, each from its own setting
     *
     * @return void
     */
    public function testTaxBasesAreIndependent(): void
    {
        $lineOnly = new Config(
            new FakeScopeConfig(['hryvinskyi_datalayer/item/line_price_includes_tax' => '1']),
            new Json(),
            new DraftSettings()
        );

        self::assertFalse($lineOnly->isCataloguePriceIncludingTax(3));
        self::assertTrue($lineOnly->isLinePriceIncludingTax(3));
    }

    /**
     * While a draft is open, drafted flags and texts answer from the draft, including a drafted empty value over a
     * stored one; paths not drafted still read the store; after the draft, the stored values come back
     *
     * @return void
     */
    public function testDraftedSettingsAnswerBeforeStoredOnes(): void
    {
        $draft = new DraftSettings();
        $config = new Config(
            new FakeScopeConfig([
                'hryvinskyi_datalayer/general/enabled' => '1',
                'hryvinskyi_datalayer/gtm/container_id' => 'GTM-STORED',
                'hryvinskyi_datalayer/item/brand_attribute' => 'manufacturer',
                'hryvinskyi_datalayer/events/disabled' => 'login',
                'hryvinskyi_datalayer/item/hidden_price_handling' => 'skip_event',
            ]),
            new Json(),
            $draft
        );

        $drafted = $draft->withDraft(
            [
                'hryvinskyi_datalayer/general/enabled' => '0',
                'hryvinskyi_datalayer/gtm/container_id' => ' GTM-DRAFT ',
                'hryvinskyi_datalayer/item/brand_attribute' => '',
                'hryvinskyi_datalayer/purchase/deduct_tax' => 'true',
            ],
            static fn (): array => [
                $config->isEnabled(1),
                $config->getContainerId(1),
                $config->getBrandAttribute(1),
                $config->isPurchaseTaxDeducted(1),
                $config->getDisabledEvents(1),
                $config->getHiddenPriceHandling(1),
            ]
        );

        self::assertSame([false, 'GTM-DRAFT', null, true, ['login'], HiddenPriceHandling::SkipEvent], $drafted);
        self::assertTrue($config->isEnabled(1));
        self::assertSame('GTM-STORED', $config->getContainerId(1));
        self::assertSame('manufacturer', $config->getBrandAttribute(1));
        self::assertFalse($config->isPurchaseTaxDeducted(1));
    }

    /**
     * A drafted flag is on only for "1" and "true"; "0", an empty value and any other text read as off
     *
     * @return void
     */
    public function testDraftedFlagIsOnOnlyForOneOrTrue(): void
    {
        $draft = new DraftSettings();
        $config = new Config(
            new FakeScopeConfig(['hryvinskyi_datalayer/general/enabled' => '1']),
            new Json(),
            $draft
        );

        $readings = ['1' => true, 'true' => true, ' 1 ' => true, '0' => false, '' => false, 'yes' => false];
        foreach ($readings as $raw => $on) {
            self::assertSame(
                $on,
                $draft->withDraft(
                    ['hryvinskyi_datalayer/general/enabled' => (string) $raw],
                    static fn (): bool => $config->isEnabled(1)
                ),
                sprintf('Drafted as "%s".', $raw)
            );
        }
    }
}
