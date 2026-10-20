<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Setup\Patch\Data;

use Hryvinskyi\DataLayer\Api\Migration\GoogleSettingsTranslatorInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Moves the stored settings of Magento's own Google modules (gtag, the Google Ads conversion pixel, Universal
 * Analytics, Google Optimize), which this module replaces, onto its gtag and Google Ads settings, then deletes the old
 * rows
 *
 * The old modules are named by their setting paths only; they are switched off where this module runs, so none of
 * their code is used. What each old row becomes is decided by the settings translator; this patch reads the rows,
 * writes what it decides and deletes every old row, in one transaction: a failure leaves the settings as they were.
 * With no old row left, applying the patch again changes nothing.
 */
class MigrateMagentoGoogleSettings implements DataPatchInterface
{
    private const string CONFIG_TABLE = 'core_config_data';
    private const string STORE_TABLE = 'store';

    /**
     * The old modules' setting paths
     */
    private const array OLD_PREFIXES = ['google/gtag/', 'google/adwords/', 'google/analytics/', 'google/optimizer/'];

    /**
     * The settings this patch can write
     */
    private const array NEW_PREFIXES = ['hryvinskyi_datalayer/gtag/', 'hryvinskyi_datalayer/ads/'];

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param GoogleSettingsTranslatorInterface $translator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly GoogleSettingsTranslatorInterface $translator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Write the data layer settings the old rows become, then delete the old rows
     *
     * @return $this
     * @throws \Throwable When a row cannot be read, written or deleted; nothing is changed then
     */
    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable(self::CONFIG_TABLE);

        $oldRows = $this->storedRows(self::OLD_PREFIXES);
        if ($oldRows === []) {
            return $this;
        }

        $connection->beginTransaction();
        try {
            $storedKeys = [];
            foreach ($this->storedRows(self::NEW_PREFIXES) as $row) {
                $storedKeys[$row['scope'] . '|' . $row['scope_id'] . '|' . $row['path']] = true;
            }
            $writes = $this->translator->translate($oldRows, $storedKeys, $this->storeWebsites());
            foreach ($writes as $write) {
                $connection->insert($table, $write);
            }
            foreach (self::OLD_PREFIXES as $prefix) {
                $connection->delete($table, ['path LIKE ?' => $prefix . '%']);
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        $this->logger->info('Moved the settings of Magento\'s Google modules to the data layer.', [
            'old_rows' => count($oldRows),
            'written' => count($writes),
        ]);

        return $this;
    }

    /**
     * Read the stored rows under some path prefixes, oldest first
     *
     * A stored NULL reads as an empty value.
     *
     * @param list<string> $prefixes
     * @return list<array{scope: string, scope_id: int, path: string, value: string}>
     */
    private function storedRows(array $prefixes): array
    {
        $connection = $this->moduleDataSetup->getConnection();
        $conditions = array_map(
            static fn (string $prefix): string => $connection->quoteInto('path LIKE ?', $prefix . '%'),
            $prefixes
        );
        $select = $connection->select()
            ->from($this->moduleDataSetup->getTable(self::CONFIG_TABLE), ['scope', 'scope_id', 'path', 'value'])
            ->where(implode(' OR ', $conditions))
            ->order('config_id');

        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = $row['value'] ?? null;
            $rows[] = [
                'scope' => is_scalar($row['scope'] ?? null) ? (string) $row['scope'] : '',
                'scope_id' => is_numeric($row['scope_id'] ?? null) ? (int) $row['scope_id'] : 0,
                'path' => is_scalar($row['path'] ?? null) ? (string) $row['path'] : '',
                'value' => is_scalar($value) ? (string) $value : '',
            ];
        }

        return $rows;
    }

    /**
     * Read the website of every store view
     *
     * @return array<int, int> Keyed by store view id
     */
    private function storeWebsites(): array
    {
        $connection = $this->moduleDataSetup->getConnection();
        $select = $connection->select()
            ->from($this->moduleDataSetup->getTable(self::STORE_TABLE), ['store_id', 'website_id']);

        $websites = [];
        foreach ($connection->fetchPairs($select) as $storeId => $websiteId) {
            if (is_numeric($websiteId)) {
                $websites[(int) $storeId] = (int) $websiteId;
            }
        }

        return $websites;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
