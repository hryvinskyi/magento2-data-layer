<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Migration;

use Hryvinskyi\DataLayer\Model\Migration\GoogleSettingsTranslator;
use Hryvinskyi\DataLayer\Test\Unit\Model\RecordingLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the stored rows of Magento's Google modules become, scope by scope, read the way those modules read them
 */
class GoogleSettingsTranslatorTest extends TestCase
{
    private const WEBSITE = 2;
    private const STORE = 3;
    private const OTHER_STORE = 5;

    /**
     * The decision table: old rows at their scopes, and the data layer rows written for them
     *
     * @param list<array{0: string, 1: int, 2: string, 3: string}> $old Scope, scope id, old path, value
     * @param array<string, string> $expected `<scope>|<scope id>|<new path below the section>` => value
     * @return void
     * @dataProvider decisionProvider
     */
    #[DataProvider('decisionProvider')]
    public function testDecisions(array $old, array $expected): void
    {
        self::assertSame($expected, $this->translated($old));
    }

    /**
     * Get old rows and the data layer rows they become
     *
     * @return array<string, array{list<array{0: string, 1: int, 2: string, 3: string}>, array<string, string>}>
     */
    public static function decisionProvider(): array
    {
        return [
            'GA4 on at default' => [
                [
                    ['default', 0, 'google/gtag/analytics4/active', '1'],
                    ['default', 0, 'google/gtag/analytics4/measurement_id', ' g-abc123 '],
                ],
                ['default|0|gtag/measurement_id' => 'G-ABC123'],
            ],
            'GA4 switched off below a parent that has it on' => [
                [
                    ['default', 0, 'google/gtag/analytics4/active', '1'],
                    ['default', 0, 'google/gtag/analytics4/measurement_id', 'G-ABC123'],
                    ['stores', self::STORE, 'google/gtag/analytics4/active', '0'],
                ],
                ['default|0|gtag/measurement_id' => 'G-ABC123', 'stores|3|gtag/measurement_id' => ''],
            ],
            'GA4 switched on below a parent that holds the id but is off' => [
                [
                    ['default', 0, 'google/gtag/analytics4/active', '0'],
                    ['default', 0, 'google/gtag/analytics4/measurement_id', 'G-ABC123'],
                    ['websites', self::WEBSITE, 'google/gtag/analytics4/active', '1'],
                ],
                ['websites|2|gtag/measurement_id' => 'G-ABC123'],
            ],
            'a store view inherits its website' => [
                [
                    ['websites', self::WEBSITE, 'google/gtag/analytics4/active', '1'],
                    ['stores', self::STORE, 'google/gtag/analytics4/measurement_id', 'G-STORE1'],
                    ['stores', self::OTHER_STORE, 'google/gtag/analytics4/measurement_id', 'G-OTHER1'],
                ],
                [
                    'stores|3|gtag/measurement_id' => 'G-STORE1',
                    'stores|5|gtag/measurement_id' => '',
                    'websites|2|gtag/measurement_id' => '',
                ],
            ],
            'GA4 off at default writes nothing' => [
                [['default', 0, 'google/gtag/analytics4/active', '0']],
                [],
            ],
            'conversion pixel switched off keeps its account' => [
                [
                    ['stores', self::STORE, 'google/adwords/active', '0'],
                    ['stores', self::STORE, 'google/adwords/conversion_id', '987654321'],
                    ['stores', self::STORE, 'google/adwords/conversion_label', 'AbCd_Ef-1'],
                ],
                [
                    'stores|3|ads/conversion_id' => 'AW-987654321',
                    'stores|3|ads/conversion_label' => 'AbCd_Ef-1',
                    'stores|3|ads/enabled' => '0',
                ],
            ],
            'gtag Ads wins over the pixel where it is on' => [
                [
                    ['default', 0, 'google/adwords/active', '1'],
                    ['default', 0, 'google/adwords/conversion_id', '111'],
                    ['default', 0, 'google/adwords/conversion_label', 'pixel'],
                    ['default', 0, 'google/adwords/conversion_value_type', '0'],
                    ['default', 0, 'google/adwords/conversion_value', '9'],
                    ['default', 0, 'google/gtag/adwords/active', '1'],
                    ['default', 0, 'google/gtag/adwords/conversion_id', 'AW-222'],
                    ['default', 0, 'google/gtag/adwords/conversion_label', 'gtag'],
                ],
                [
                    'default|0|ads/conversion_id' => 'AW-222',
                    'default|0|ads/conversion_label' => 'gtag',
                    'default|0|ads/enabled' => '1',
                ],
            ],
            'the pixel where gtag Ads is off, with its constant value' => [
                [
                    ['default', 0, 'google/gtag/adwords/active', '0'],
                    ['default', 0, 'google/gtag/adwords/conversion_id', 'AW-222'],
                    ['websites', self::WEBSITE, 'google/adwords/active', '1'],
                    ['websites', self::WEBSITE, 'google/adwords/conversion_id', '111'],
                    ['websites', self::WEBSITE, 'google/adwords/conversion_label', 'pixel'],
                    ['websites', self::WEBSITE, 'google/adwords/conversion_value_type', '0'],
                    ['websites', self::WEBSITE, 'google/adwords/conversion_value', ' 12.50 '],
                ],
                [
                    'default|0|ads/conversion_id' => 'AW-222',
                    'websites|2|ads/conversion_id' => 'AW-111',
                    'websites|2|ads/conversion_label' => 'pixel',
                    'websites|2|ads/enabled' => '1',
                    'websites|2|ads/fixed_value' => '12.50',
                    'websites|2|ads/value_source' => 'fixed',
                ],
            ],
            'a dynamic pixel value needs no fixed amount' => [
                [
                    ['stores', self::STORE, 'google/adwords/conversion_value_type', '1'],
                    ['stores', self::STORE, 'google/adwords/conversion_value', '5'],
                ],
                ['stores|3|ads/value_source' => 'purchase'],
            ],
            'gtag Ads switched on below the pixel' => [
                [
                    ['default', 0, 'google/adwords/active', '1'],
                    ['default', 0, 'google/adwords/conversion_id', '111'],
                    ['default', 0, 'google/adwords/conversion_label', 'pixel'],
                    ['default', 0, 'google/adwords/conversion_value_type', '0'],
                    ['stores', self::STORE, 'google/gtag/adwords/active', '1'],
                    ['stores', self::STORE, 'google/gtag/adwords/conversion_id', 'AW-333'],
                    ['stores', self::STORE, 'google/gtag/adwords/conversion_label', 'gtag'],
                ],
                [
                    'default|0|ads/conversion_id' => 'AW-111',
                    'default|0|ads/conversion_label' => 'pixel',
                    'default|0|ads/enabled' => '1',
                    'default|0|ads/fixed_value' => '0',
                    'default|0|ads/value_source' => 'fixed',
                    'stores|3|ads/conversion_id' => 'AW-333',
                    'stores|3|ads/conversion_label' => 'gtag',
                    'stores|3|ads/enabled' => '1',
                    'stores|3|ads/value_source' => 'purchase',
                ],
            ],
            'Universal Analytics, Optimize and the pixel\'s looks are dropped' => [
                [
                    ['default', 0, 'google/analytics/active', '1'],
                    ['default', 0, 'google/analytics/account', 'UA-1-1'],
                    ['default', 0, 'google/analytics/experiments', '1'],
                    ['default', 0, 'google/optimizer/active', '1'],
                    ['default', 0, 'google/gtag/analytics4/experiments', '1'],
                    ['default', 0, 'google/adwords/conversion_color', 'FFFFFF'],
                    ['default', 0, 'google/adwords/send_currency', '1'],
                ],
                [],
            ],
        ];
    }

    /**
     * A setting the data layer already stores at a scope is kept, and that is logged with the scope
     *
     * @return void
     */
    public function testStoredSettingIsKept(): void
    {
        $logger = new RecordingLogger();
        $writes = (new GoogleSettingsTranslator($logger))->translate(
            $this->rows([
                ['websites', self::WEBSITE, 'google/gtag/analytics4/active', '1'],
                ['websites', self::WEBSITE, 'google/gtag/analytics4/measurement_id', 'G-OLD'],
            ]),
            ['websites|2|hryvinskyi_datalayer/gtag/measurement_id' => true],
            $this->storeWebsites()
        );

        self::assertSame([], $writes);
        self::assertContains(
            ['info', 'hryvinskyi_datalayer/gtag/measurement_id', 'websites', self::WEBSITE],
            $this->logged($logger)
        );
    }

    /**
     * An old value the data layer cannot use becomes empty below the default scope and is reported as a warning
     *
     * @return void
     */
    public function testUnusableValuesBecomeEmptyWithAWarning(): void
    {
        $logger = new RecordingLogger();
        $writes = $this->translated([
            ['stores', self::STORE, 'google/gtag/analytics4/active', '1'],
            ['stores', self::STORE, 'google/gtag/analytics4/measurement_id', 'UA-1234-1'],
            ['stores', self::STORE, 'google/adwords/conversion_id', 'AW-12x'],
            ['stores', self::STORE, 'google/adwords/conversion_value_type', '0'],
            ['stores', self::STORE, 'google/adwords/conversion_value', '-3'],
        ], $logger);

        self::assertSame(
            [
                'stores|3|ads/conversion_id' => '',
                'stores|3|ads/fixed_value' => '',
                'stores|3|ads/value_source' => 'fixed',
                'stores|3|gtag/measurement_id' => '',
            ],
            $writes
        );
        $warnings = array_values(array_filter(
            $logger->records,
            static fn (array $record): bool => $record['level'] === 'warning'
        ));
        self::assertCount(3, $warnings);
        foreach ($warnings as $warning) {
            self::assertSame('stores', $warning['context']['scope'] ?? null);
            self::assertSame(self::STORE, $warning['context']['scope_id'] ?? null);
        }
    }

    /**
     * Every dropped row and every written row is logged with its path and scope
     *
     * @return void
     */
    public function testEveryDecisionIsLoggedWithItsScope(): void
    {
        $logger = new RecordingLogger();
        $this->translated([
            ['stores', self::STORE, 'google/analytics/account', 'UA-1-1'],
            ['stores', self::STORE, 'google/optimizer/active', '1'],
            ['default', 0, 'google/gtag/analytics4/active', '1'],
            ['default', 0, 'google/gtag/analytics4/measurement_id', 'G-ABC123'],
        ], $logger);

        self::assertSame(
            [
                ['info', 'google/analytics/account', 'stores', self::STORE],
                ['info', 'google/optimizer/active', 'stores', self::STORE],
                ['info', 'hryvinskyi_datalayer/gtag/measurement_id', 'default', 0],
            ],
            $this->logged($logger)
        );
        $reasons = array_map(
            static fn (array $record): string => is_string($record['context']['reason'] ?? null)
                ? $record['context']['reason']
                : '',
            $logger->records
        );
        self::assertStringContainsString('Universal Analytics', $reasons[0]);
        self::assertStringContainsString('Optimize', $reasons[1]);
    }

    /**
     * Translate rows and key the result by scope and new path, sorted
     *
     * @param list<array{0: string, 1: int, 2: string, 3: string}> $old
     * @param RecordingLogger|null $logger
     * @return array<string, string>
     */
    private function translated(array $old, ?RecordingLogger $logger = null): array
    {
        $writes = (new GoogleSettingsTranslator($logger ?? new RecordingLogger()))
            ->translate($this->rows($old), [], $this->storeWebsites());

        $keyed = [];
        foreach ($writes as $write) {
            $path = substr($write['path'], strlen('hryvinskyi_datalayer/'));
            $keyed[$write['scope'] . '|' . $write['scope_id'] . '|' . $path] = $write['value'];
        }
        ksort($keyed);

        return $keyed;
    }

    /**
     * Turn short rows into stored rows
     *
     * @param list<array{0: string, 1: int, 2: string, 3: string}> $old
     * @return list<array{scope: string, scope_id: int, path: string, value: string}>
     */
    private function rows(array $old): array
    {
        return array_map(
            static fn (array $row): array => ['scope' => $row[0], 'scope_id' => $row[1], 'path' => $row[2],
                'value' => $row[3]],
            $old
        );
    }

    /**
     * Store view 3 sits in website 2; store view 5 in another website
     *
     * @return array<int, int>
     */
    private function storeWebsites(): array
    {
        return [self::STORE => self::WEBSITE, self::OTHER_STORE => 7];
    }

    /**
     * The level, path and scope of every log record
     *
     * @param RecordingLogger $logger
     * @return list<array{mixed, mixed, mixed, mixed}>
     */
    private function logged(RecordingLogger $logger): array
    {
        return array_map(
            static fn (array $record): array => [
                $record['level'],
                $record['context']['path'] ?? null,
                $record['context']['scope'] ?? null,
                $record['context']['scope_id'] ?? null,
            ],
            $logger->records
        );
    }
}
