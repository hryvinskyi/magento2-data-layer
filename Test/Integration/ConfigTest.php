<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\FixedChannelsInterface;
use Hryvinskyi\DataLayer\Model\Config\CheckoutStepValueBasis;
use Hryvinskyi\DataLayer\Model\Config\HiddenPriceHandling;
use Hryvinskyi\DataLayer\Model\Config\ImpressionTrigger;
use Hryvinskyi\DataLayer\Model\Config\ValueBasis;
use Hryvinskyi\DataLayerApi\Api\Data\EventChannel;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Hryvinskyi\DataLayerApi\Api\EventChannelPolicyInterface;
use PHPUnit\Framework\TestCase;

/**
 * The store view's stored settings come back typed through the settings contract, and drive event availability and
 * routing
 *
 * @magentoAppArea frontend
 */
class ConfigTest extends TestCase
{
    use ResolvesServices;

    /**
     * The module's defaults hold when nothing is stored
     *
     * @return void
     */
    public function testDefaultsHoldWhenNothingIsStored(): void
    {
        $config = $this->service(ConfigInterface::class);

        self::assertFalse($config->isEnabled());
        self::assertNull($config->getContainerId());
        self::assertSame('www.googletagmanager.com', $config->getScriptHost());
        $fixedChannels = $this->service(FixedChannelsInterface::class)->get();
        foreach ($config->getDisabledEvents() as $disabledByDefault) {
            self::assertSame(
                EventChannel::Server,
                $fixedChannels[$disabledByDefault] ?? null,
                sprintf('Only an event sent from the server alone is off by default; "%s" is not.', $disabledByDefault)
            );
        }
        self::assertSame('sku', $config->getIdentifierAttribute());
        self::assertNull($config->getBrandAttribute());
        self::assertSame(HiddenPriceHandling::OmitPrice, $config->getHiddenPriceHandling());
        self::assertFalse($config->isCataloguePriceIncludingTax());
        self::assertFalse($config->isLinePriceIncludingTax());
        self::assertSame(CheckoutStepValueBasis::Items, $config->getCheckoutStepValueBasis());
        self::assertSame(ValueBasis::GrandTotal, $config->getPurchaseValueBasis());
        self::assertFalse($config->isPurchaseTaxDeducted());
        self::assertTrue($config->isPurchaseTaxIncluded());
        self::assertTrue($config->isPurchaseShippingIncluded());
        self::assertSame(ImpressionTrigger::Load, $config->getImpressionTrigger());
        self::assertSame([], $config->getChannelRouting());
    }

    /**
     * Store-view values come back typed
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/gtm/container_id GTM-TEST123
     * @magentoConfigFixture current_store hryvinskyi_datalayer/gtm/script_host sgtm.example.test
     * @magentoConfigFixture current_store hryvinskyi_datalayer/events/disabled login,sign_up
     * @magentoConfigFixture current_store hryvinskyi_datalayer/item/identifier_attribute entity_id
     * @magentoConfigFixture current_store hryvinskyi_datalayer/item/brand_attribute manufacturer
     * @magentoConfigFixture current_store hryvinskyi_datalayer/item/hidden_price_handling skip_event
     * @magentoConfigFixture current_store hryvinskyi_datalayer/item/catalogue_price_includes_tax 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/item/line_price_includes_tax 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/checkout/step_value_basis grand_total
     * @magentoConfigFixture current_store hryvinskyi_datalayer/purchase/value_basis subtotal
     * @magentoConfigFixture current_store hryvinskyi_datalayer/purchase/deduct_tax 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/purchase/include_tax 0
     * @magentoConfigFixture current_store hryvinskyi_datalayer/purchase/include_shipping 0
     * @magentoConfigFixture current_store hryvinskyi_datalayer/lists/impression_trigger visible
     * @return void
     */
    public function testStoredValuesComeBackTyped(): void
    {
        $config = $this->service(ConfigInterface::class);

        self::assertTrue($config->isEnabled());
        self::assertSame('GTM-TEST123', $config->getContainerId());
        self::assertSame('sgtm.example.test', $config->getScriptHost());
        self::assertSame(['login', 'sign_up'], $config->getDisabledEvents());
        self::assertSame('entity_id', $config->getIdentifierAttribute());
        self::assertSame('manufacturer', $config->getBrandAttribute());
        self::assertSame(HiddenPriceHandling::SkipEvent, $config->getHiddenPriceHandling());
        self::assertTrue($config->isCataloguePriceIncludingTax());
        self::assertTrue($config->isLinePriceIncludingTax());
        self::assertSame(CheckoutStepValueBasis::GrandTotal, $config->getCheckoutStepValueBasis());
        self::assertSame(ValueBasis::Subtotal, $config->getPurchaseValueBasis());
        self::assertTrue($config->isPurchaseTaxDeducted());
        self::assertFalse($config->isPurchaseTaxIncluded());
        self::assertFalse($config->isPurchaseShippingIncluded());
        self::assertSame(ImpressionTrigger::Visible, $config->getImpressionTrigger());
    }

    /**
     * A stored value outside a setting's known values reads as its default
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/item/hidden_price_handling show_everything
     * @magentoConfigFixture current_store hryvinskyi_datalayer/purchase/value_basis net
     * @magentoConfigFixture current_store hryvinskyi_datalayer/lists/impression_trigger scroll
     * @magentoConfigFixture current_store hryvinskyi_datalayer/checkout/step_value_basis subtotal
     * @magentoConfigFixture current_store hryvinskyi_datalayer/gtm/container_id UA-12345
     * @return void
     */
    public function testUnknownStoredValuesReadAsDefaults(): void
    {
        $config = $this->service(ConfigInterface::class);

        self::assertSame(HiddenPriceHandling::OmitPrice, $config->getHiddenPriceHandling());
        self::assertSame(ValueBasis::GrandTotal, $config->getPurchaseValueBasis());
        self::assertSame(ImpressionTrigger::Load, $config->getImpressionTrigger());
        self::assertSame(CheckoutStepValueBasis::Items, $config->getCheckoutStepValueBasis());
        self::assertNull($config->getContainerId());
    }

    /**
     * The stored settings decide which events are reported and where they go
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/events/disabled login
     * @magentoConfigFixture current_store hryvinskyi_datalayer/channels/routing {"_1":{"event":"purchase","channel":"server"},"_2":{"event":"add_to_cart","channel":"both"},"_3":{"event":"made_up","channel":"server"}}
     * @return void
     */
    public function testStoredSettingsDriveAvailabilityAndRouting(): void
    {
        $availability = $this->service(EventAvailabilityInterface::class);
        $channels = $this->service(EventChannelPolicyInterface::class);

        self::assertTrue($availability->isEnabled('purchase'));
        self::assertFalse($availability->isEnabled('login'));
        self::assertSame(EventChannel::Server, $channels->channelOf('purchase'));
        self::assertSame(EventChannel::Both, $channels->channelOf('add_to_cart'));
        self::assertSame(EventChannel::Browser, $channels->channelOf('made_up'));
        self::assertSame(EventChannel::Browser, $channels->channelOf('view_cart'));
    }
}
