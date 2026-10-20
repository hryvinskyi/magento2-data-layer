<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration;

use Hryvinskyi\DataLayer\Api\Config\DraftSettingReaderInterface;
use Hryvinskyi\DataLayer\Api\Config\DraftSettingsInterface;
use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\PriceVisibilityOverrideInterface;
use Hryvinskyi\DataLayer\Api\PriceVisibilityOverrideReaderInterface;
use Hryvinskyi\DataLayer\Model\Config\DraftSettings;
use Hryvinskyi\DataLayer\Model\PriceVisibilityOverride;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Hryvinskyi\DataLayerApi\Api\PriceVisibilityInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use PHPUnit\Framework\TestCase;

/**
 * Unsaved settings drafted through the container are what the settings contract and the event availability read
 * while the work runs, and the stored rows come back after it; a forced price-visibility answer is what price
 * visibility gives while its work runs. Each override is one shared instance behind both of its contracts.
 *
 * @magentoAppArea frontend
 */
class RequestOverridesTest extends TestCase
{
    use ResolvesServices;

    /**
     * Both draft contracts resolve to one shared draft, and both override contracts to one shared override
     *
     * @return void
     */
    public function testEachOverrideIsOneSharedInstance(): void
    {
        $draft = $this->service(DraftSettingsInterface::class);
        $override = $this->service(PriceVisibilityOverrideInterface::class);

        self::assertInstanceOf(DraftSettings::class, $draft);
        self::assertSame($draft, $this->service(DraftSettingReaderInterface::class));
        self::assertInstanceOf(PriceVisibilityOverride::class, $override);
        self::assertSame($override, $this->service(PriceVisibilityOverrideReaderInterface::class));
    }

    /**
     * Stored store-view rows are replaced by the draft while it is open, and read again once it is closed
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/gtm/container_id GTM-STORED
     * @magentoConfigFixture current_store hryvinskyi_datalayer/events/disabled login
     * @return void
     */
    public function testDraftOverridesStoredRowsOnlyWhileOpen(): void
    {
        $config = $this->service(ConfigInterface::class);
        $availability = $this->service(EventAvailabilityInterface::class);

        $drafted = $this->service(DraftSettingsInterface::class)->withDraft(
            [
                'hryvinskyi_datalayer/gtm/container_id' => 'GTM-DRAFT',
                'hryvinskyi_datalayer/events/disabled' => 'view_item',
            ],
            static fn (): array => [
                $config->getContainerId(),
                $availability->isEnabled('view_item'),
                $availability->isEnabled('login'),
                $config->isEnabled(),
            ]
        );

        self::assertSame(['GTM-DRAFT', false, true, true], $drafted);
        self::assertSame('GTM-STORED', $config->getContainerId());
        self::assertTrue($availability->isEnabled('view_item'));
        self::assertFalse($availability->isEnabled('login'));
    }

    /**
     * Price visibility gives the forced answer, either way, only while the override is open
     *
     * @return void
     */
    public function testForcedAnswerHoldsOnlyWhileOpen(): void
    {
        $visibility = $this->service(PriceVisibilityInterface::class);
        $override = $this->service(PriceVisibilityOverrideInterface::class);
        $product = $this->service(ProductInterfaceFactory::class)->create();
        $unforced = $visibility->isPriceVisible($product);

        self::assertFalse($override->withAnswer(false, static fn (): bool => $visibility->isPriceVisible($product)));
        self::assertTrue($override->withAnswer(true, static fn (): bool => $visibility->isPriceVisible($product)));
        self::assertSame($unforced, $visibility->isPriceVisible($product));
    }
}
