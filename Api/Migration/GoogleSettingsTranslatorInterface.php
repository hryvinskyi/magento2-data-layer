<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Migration;

/**
 * Decides which data layer settings the stored rows of Magento's own Google modules become
 *
 * It reads only what it is given and stores nothing; it reports each decision to the log with its scope.
 */
interface GoogleSettingsTranslatorInterface
{
    /**
     * Get the rows to write for the given old rows
     *
     * @param array $oldRows Every stored row under the old modules' paths
     * @param array $storedKeys The data layer settings already stored, keyed `<scope>|<scope id>|<path>`
     * @param array $storeWebsites The website of each store view, keyed by store view id
     * @return array The rows to write, with full data layer paths
     * @phpstan-param list<array{scope: string, scope_id: int, path: string, value: string}> $oldRows
     * @phpstan-param array<string, true> $storedKeys
     * @phpstan-param array<int, int> $storeWebsites
     * @phpstan-return list<array{scope: string, scope_id: int, path: string, value: string}>
     */
    public function translate(array $oldRows, array $storedKeys, array $storeWebsites): array;
}
