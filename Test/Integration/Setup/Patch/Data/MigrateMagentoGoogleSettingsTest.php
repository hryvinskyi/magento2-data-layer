<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration\Setup\Patch\Data;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\Migration\GoogleSettingsTranslatorInterface;
use Hryvinskyi\DataLayer\Model\Config\AdsValueSource;
use Hryvinskyi\DataLayer\Setup\Patch\Data\MigrateMagentoGoogleSettings;
use Hryvinskyi\DataLayer\Test\Integration\ResolvesServices;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * The stored rows of Magento's Google modules become the data layer's gtag and Google Ads settings at the scopes they
 * were saved at, what has no successor is dropped, and every old row is gone
 *
 * @magentoDbIsolation enabled
 * @magentoAppArea adminhtml
 */
class MigrateMagentoGoogleSettingsTest extends TestCase
{
    use ResolvesServices;

    private const string NEW = 'hryvinskyi_datalayer/';

    /**
     * @var AdapterInterface
     */
    private AdapterInterface $connection;

    /**
     * @var string
     */
    private string $table;

    /**
     * @var int
     */
    private int $websiteId;

    /**
     * @var int
     */
    private int $storeId;

    /**
     * @var list<array{level: string, message: string, context: array<mixed>}>
     */
    private array $logged = [];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $resource = $this->service(ResourceConnection::class);
        $this->connection = $resource->getConnection();
        $this->table = $resource->getTableName('core_config_data');
        $store = $this->service(StoreManagerInterface::class)->getStore('default');
        $this->storeId = (int) $store->getId();
        $this->websiteId = (int) $store->getWebsiteId();
        foreach (['google/%', self::NEW . 'gtag/%', self::NEW . 'ads/%'] as $paths) {
            $this->connection->delete($this->table, ['path LIKE ?' => $paths]);
        }
    }

    /**
     * gtag and conversion pixel rows overlap across the default scope, a website and a store view: each scope ends up
     * with what the old modules used there, Universal Analytics and Optimize are dropped, and no old row is left
     *
     * @return void
     */
    public function testEveryScopeGetsWhatTheOldModulesUsedThere(): void
    {
        $this->seed('default', 0, [
            'google/gtag/analytics4/active' => '1',
            'google/gtag/analytics4/measurement_id' => 'G-DEFAULT1',
            'google/adwords/active' => '1',
            'google/adwords/conversion_id' => '1111',
            'google/adwords/conversion_label' => 'pixelLabel',
            'google/adwords/conversion_value_type' => '0',
            'google/adwords/conversion_value' => '7.5',
            'google/adwords/conversion_color' => 'FFFFFF',
            'google/analytics/active' => '1',
            'google/analytics/account' => 'UA-1111-1',
            'google/analytics/experiments' => '1',
            'google/optimizer/active' => '1',
        ]);
        $this->seed('websites', $this->websiteId, [
            'google/gtag/adwords/active' => '1',
            'google/gtag/adwords/conversion_id' => 'AW-2222',
            'google/gtag/adwords/conversion_label' => 'gtagLabel',
        ]);
        $this->seed('stores', $this->storeId, [
            'google/gtag/analytics4/active' => '0',
            'google/adwords/active' => '0',
            'google/gtag/adwords/active' => '0',
        ]);

        $this->patch()->apply();

        self::assertSame(
            [
                'ads/conversion_id' => 'AW-1111',
                'ads/conversion_label' => 'pixelLabel',
                'ads/enabled' => '1',
                'ads/fixed_value' => '7.5',
                'ads/value_source' => 'fixed',
                'gtag/measurement_id' => 'G-DEFAULT1',
            ],
            $this->newRows('default', 0)
        );
        self::assertSame(
            [
                'ads/conversion_id' => 'AW-2222',
                'ads/conversion_label' => 'gtagLabel',
                'ads/enabled' => '1',
                'ads/value_source' => 'purchase',
            ],
            $this->newRows('websites', $this->websiteId)
        );
        self::assertSame(
            ['ads/enabled' => '0', 'ads/value_source' => 'purchase', 'gtag/measurement_id' => ''],
            $this->newRows('stores', $this->storeId)
        );
        self::assertSame(0, $this->oldRowCount());

        $this->service(ReinitableConfigInterface::class)->reinit();
        $config = $this->service(ConfigInterface::class);
        self::assertNull($config->getGtagMeasurementId($this->storeId));
        self::assertFalse($config->isAdsConversionEnabled($this->storeId));
        self::assertSame('AW-2222', $config->getAdsConversionId($this->storeId));
        self::assertSame(AdsValueSource::Purchase, $config->getAdsValueSource($this->storeId));

        $dropped = array_filter(
            $this->logged,
            static fn (array $entry): bool => is_string($entry['context']['reason'] ?? null)
        );
        self::assertSame(
            [
                'google/adwords/conversion_color',
                'google/analytics/account',
                'google/analytics/active',
                'google/analytics/experiments',
                'google/optimizer/active',
            ],
            $this->sorted(array_map(static fn (array $entry): mixed => $entry['context']['path'] ?? null, $dropped))
        );
    }

    /**
     * A setting already saved for the data layer at a scope is kept; the old rows are still deleted
     *
     * @return void
     */
    public function testASettingAlreadyStoredForTheDataLayerIsKept(): void
    {
        $this->connection->insert($this->table, [
            'scope' => 'websites',
            'scope_id' => $this->websiteId,
            'path' => self::NEW . 'gtag/measurement_id',
            'value' => 'G-CHOSEN1',
        ]);
        $this->seed('websites', $this->websiteId, [
            'google/gtag/analytics4/active' => '1',
            'google/gtag/analytics4/measurement_id' => 'G-OLD1',
        ]);

        $this->patch()->apply();

        self::assertSame(['gtag/measurement_id' => 'G-CHOSEN1'], $this->newRows('websites', $this->websiteId));
        self::assertSame(0, $this->oldRowCount());
    }

    /**
     * Applying the patch again, with no old row left, changes nothing
     *
     * @return void
     */
    public function testApplyingAgainChangesNothing(): void
    {
        $this->seed('stores', $this->storeId, [
            'google/adwords/active' => '0',
            'google/adwords/conversion_id' => '987654321',
            'google/adwords/conversion_label' => 'AbCd_Ef-1',
        ]);
        $this->patch()->apply();
        $before = $this->newRows('stores', $this->storeId);

        $this->patch()->apply();

        self::assertSame(
            ['ads/conversion_id' => 'AW-987654321', 'ads/conversion_label' => 'AbCd_Ef-1', 'ads/enabled' => '0'],
            $before
        );
        self::assertSame($before, $this->newRows('stores', $this->storeId));
        self::assertSame(0, $this->oldRowCount());
    }

    /**
     * Build the patch through the real container, with a logger that records what it reports
     *
     * @return MigrateMagentoGoogleSettings
     */
    private function patch(): MigrateMagentoGoogleSettings
    {
        /**
         * @param string $level
         * @param string $message
         * @param array<mixed> $context
         * @return void
         */
        $record = function (string $level, string $message, array $context): void {
            $this->logged[] = ['level' => $level, 'message' => $message, 'context' => $context];
        };
        $logger = new class ($record) extends AbstractLogger {
            /**
             * @param \Closure(string, string, array<mixed>): void $record
             */
            public function __construct(private readonly \Closure $record)
            {
            }

            /**
             * @inheritDoc
             */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                ($this->record)(is_scalar($level) ? (string) $level : '', (string) $message, $context);
            }
        };
        $objectManager = Bootstrap::getObjectManager();
        $translator = $objectManager->create(GoogleSettingsTranslatorInterface::class, ['logger' => $logger]);

        return $this->checked(
            $objectManager->create(
                MigrateMagentoGoogleSettings::class,
                ['translator' => $translator, 'logger' => $logger]
            ),
            MigrateMagentoGoogleSettings::class
        );
    }

    /**
     * Store old settings at one scope
     *
     * @param string $scope
     * @param int $scopeId
     * @param array<string, string> $settings Full old path => value
     * @return void
     */
    private function seed(string $scope, int $scopeId, array $settings): void
    {
        foreach ($settings as $path => $value) {
            $this->connection->insert(
                $this->table,
                ['scope' => $scope, 'scope_id' => $scopeId, 'path' => $path, 'value' => $value]
            );
        }
    }

    /**
     * The data layer's gtag and Google Ads settings stored at one scope, keyed below its section, sorted by path
     *
     * @param string $scope
     * @param int $scopeId
     * @return array<string, string|null>
     */
    private function newRows(string $scope, int $scopeId): array
    {
        $select = $this->connection->select()
            ->from($this->table, ['path', 'value'])
            ->where('scope = ?', $scope)
            ->where('scope_id = ?', $scopeId)
            ->where(
                $this->connection->quoteInto('path LIKE ?', self::NEW . 'gtag/%') . ' OR '
                . $this->connection->quoteInto('path LIKE ?', self::NEW . 'ads/%')
            )
            ->order('path');

        $rows = [];
        foreach ($this->connection->fetchPairs($select) as $path => $value) {
            $rows[substr((string) $path, strlen(self::NEW))] = is_scalar($value) ? (string) $value : null;
        }

        return $rows;
    }

    /**
     * How many rows of the old modules are left, at any scope
     *
     * @return int
     */
    private function oldRowCount(): int
    {
        $select = $this->connection->select()
            ->from($this->table, ['COUNT(*)'])
            ->where('path LIKE ?', 'google/%');
        $count = $this->connection->fetchOne($select);

        return is_numeric($count) ? (int) $count : -1;
    }

    /**
     * Sort a list of values as text
     *
     * @param array<mixed> $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        $texts = array_map(static fn (mixed $value): string => is_string($value) ? $value : '', array_values($values));
        sort($texts);

        return $texts;
    }
}
