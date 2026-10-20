<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration;

use Hryvinskyi\DataLayer\Api\Data\EventPresetInterface;
use Hryvinskyi\DataLayer\Api\Data\KnownEventInterface;
use Hryvinskyi\DataLayer\Api\EventPresetsInterface;
use Hryvinskyi\DataLayer\Api\KnownEventsInterface;
use Hryvinskyi\DataLayer\Model\Data\EventPreset;
use Hryvinskyi\DataLayer\Model\EventPresets;
use PHPUnit\Framework\TestCase;

/**
 * The core's presets, as the container builds them, select the core's events in the known events' order, and the
 * core's known events carry stable group and source codes
 *
 * @magentoAppArea adminhtml
 */
class EventPresetsTest extends TestCase
{
    use ResolvesServices;

    /**
     * The standard preset selects every event the core registers, the minimal one product views and purchases; each
     * comes with its texts, position and glyph, and the presets come in their sort order
     *
     * @return void
     */
    public function testCorePresetsSelectTheCoresEvents(): void
    {
        $service = $this->service(EventPresetsInterface::class);
        self::assertInstanceOf(EventPresets::class, $service);
        $presets = [];
        foreach ($service->getPresets() as $preset) {
            self::assertInstanceOf(EventPreset::class, $preset);
            $presets[$preset->getKey()] = $preset;
        }
        $standard = $presets['standard'] ?? null;
        $minimal = $presets['minimal'] ?? null;
        self::assertInstanceOf(EventPresetInterface::class, $standard);
        self::assertInstanceOf(EventPresetInterface::class, $minimal);

        self::assertSame(
            ['Standard online shop', 'Product views, lists, promotions, cart, checkout steps and purchase.', 10,
                'storefront'],
            [$standard->getLabel(), $standard->getDescription(), $standard->getSortOrder(), $standard->getIcon()]
        );
        $coreEvents = [
            'view_item_list', 'select_item', 'view_item', 'view_promotion', 'select_promotion', 'add_to_wishlist',
            'add_to_cart', 'remove_from_cart', 'view_cart', 'begin_checkout', 'add_shipping_info', 'add_payment_info',
            'purchase', 'ads_conversion', 'login', 'sign_up',
        ];
        self::assertSame($coreEvents, $standard->getEvents());
        self::assertSame(
            $standard->getEvents(),
            array_values(array_intersect($this->catalogue(), $standard->getEvents())),
            'The preset lists its events in the known events\' order.'
        );

        self::assertSame(
            ['Minimal', 'Product views and purchase only.', 30, 'bolt', ['view_item', 'purchase']],
            [$minimal->getLabel(), $minimal->getDescription(), $minimal->getSortOrder(), $minimal->getIcon(),
                $minimal->getEvents()]
        );

        $order = array_keys($presets);
        self::assertLessThan(array_search('minimal', $order, true), array_search('standard', $order, true));
    }

    /**
     * A core event carries its group and source codes alongside the translated texts
     *
     * @return void
     */
    public function testCoreEventsCarryStableCodes(): void
    {
        $codes = [];
        foreach ($this->service(KnownEventsInterface::class)->getEvents() as $event) {
            $codes[$event->getName()] = [$event->getGroupCode(), $event->getSourceCode()];
        }

        self::assertSame(['browsing', 'page'], $codes['view_item'] ?? null);
        self::assertSame(['checkout', 'order'], $codes['purchase'] ?? null);
        self::assertSame(['account', 'server'], $codes['login'] ?? null);
    }

    /**
     * The known events' names in their order
     *
     * @return list<string>
     */
    private function catalogue(): array
    {
        return array_map(
            static fn (KnownEventInterface $event): string => $event->getName(),
            $this->service(KnownEventsInterface::class)->getEvents()
        );
    }
}
