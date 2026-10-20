<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Migration;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\Migration\GoogleSettingsTranslatorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Turns the stored settings of Magento's own Google modules into the data layer's gtag and Google Ads settings
 *
 * At every scope that stored an old setting, the new settings that old setting fed are written with the value the old
 * modules ended up using there, read the way they read it: a value missing at a scope is inherited from the website,
 * then the default scope, then the old module's own default. So a scope that switched a module off below a parent
 * that had it on gets an explicit "off" (an empty measurement id, Google Ads disabled) instead of inheriting the
 * parent's new value.
 * - Google Analytics 4 through gtag: the measurement id where the module was on at that scope; an empty id where it
 *   was off.
 * - Google Ads: from the gtag Ads settings where they are on at that scope, else from the conversion pixel's settings
 *   where those are on, else (both off) from whichever holds a conversion id, so a switched-off account keeps its
 *   id and label. Google Ads is enabled where either was on. A bare conversion number gets its `AW-` prefix. The
 *   gtag settings value a conversion at the purchase; the pixel's "constant" value type becomes a fixed value.
 * - Universal Analytics, Google Optimize and the pixel's language, format, colour and currency switch have no
 *   successor: they are dropped and logged.
 * A value the data layer cannot use (a malformed id or amount) becomes empty and is logged as a warning. A setting the
 * data layer already stores at a scope is kept. At the default scope a value equal to the data layer's own default is
 * not written.
 */
class GoogleSettingsTranslator implements GoogleSettingsTranslatorInterface
{
    private const string NEW_SECTION = 'hryvinskyi_datalayer/';

    private const string GA4_ACTIVE = 'google/gtag/analytics4/active';
    private const string GA4_ID = 'google/gtag/analytics4/measurement_id';
    private const string GTAG_ADS_ACTIVE = 'google/gtag/adwords/active';
    private const string GTAG_ADS_ID = 'google/gtag/adwords/conversion_id';
    private const string GTAG_ADS_LABEL = 'google/gtag/adwords/conversion_label';
    private const string PIXEL_ACTIVE = 'google/adwords/active';
    private const string PIXEL_ID = 'google/adwords/conversion_id';
    private const string PIXEL_LABEL = 'google/adwords/conversion_label';
    private const string PIXEL_VALUE_TYPE = 'google/adwords/conversion_value_type';
    private const string PIXEL_VALUE = 'google/adwords/conversion_value';

    private const string MEASUREMENT_ID = 'gtag/measurement_id';
    private const string ADS_ENABLED = 'ads/enabled';
    private const string ADS_CONVERSION_ID = 'ads/conversion_id';
    private const string ADS_CONVERSION_LABEL = 'ads/conversion_label';
    private const string ADS_VALUE_SOURCE = 'ads/value_source';
    private const string ADS_FIXED_VALUE = 'ads/fixed_value';

    /**
     * The new settings each old setting feeds at the scope it is stored at
     */
    private const array FEEDS = [
        self::GA4_ACTIVE => [self::MEASUREMENT_ID],
        self::GA4_ID => [self::MEASUREMENT_ID],
        self::GTAG_ADS_ACTIVE => [self::ADS_ENABLED, self::ADS_VALUE_SOURCE],
        self::GTAG_ADS_ID => [self::ADS_CONVERSION_ID, self::ADS_VALUE_SOURCE],
        self::GTAG_ADS_LABEL => [self::ADS_CONVERSION_LABEL, self::ADS_VALUE_SOURCE],
        self::PIXEL_ACTIVE => [self::ADS_ENABLED],
        self::PIXEL_ID => [self::ADS_CONVERSION_ID],
        self::PIXEL_LABEL => [self::ADS_CONVERSION_LABEL],
        self::PIXEL_VALUE_TYPE => [self::ADS_VALUE_SOURCE, self::ADS_FIXED_VALUE],
        self::PIXEL_VALUE => [self::ADS_FIXED_VALUE],
    ];

    /**
     * What the old modules read when no scope stores a value
     */
    private const array OLD_DEFAULTS = [
        self::GA4_ACTIVE => '0',
        self::GTAG_ADS_ACTIVE => '0',
        self::PIXEL_ACTIVE => '0',
        self::PIXEL_VALUE_TYPE => '1',
        self::PIXEL_VALUE => '0',
    ];

    /**
     * The data layer's own defaults, which the default scope does not need to store
     */
    private const array NEW_DEFAULTS = [
        self::MEASUREMENT_ID => '',
        self::ADS_ENABLED => '0',
        self::ADS_CONVERSION_ID => '',
        self::ADS_CONVERSION_LABEL => '',
        self::ADS_VALUE_SOURCE => 'purchase',
        self::ADS_FIXED_VALUE => '',
    ];

    /**
     * Why a dropped old setting has no successor, by the start of its path; the first match applies
     */
    private const array DROP_REASONS = [
        'google/analytics/experiments' => 'Google Optimize was closed by Google in 2023.',
        'google/gtag/analytics4/experiments' => 'Google Optimize was closed by Google in 2023.',
        'google/optimizer/' => 'Google Optimize was closed by Google in 2023.',
        'google/analytics/' => 'Universal Analytics was switched off by Google in 2024; GA4 has no setting that takes '
            . 'a Universal Analytics id.',
        'google/adwords/' => 'The conversion pixel\'s language, format, colour and currency settings mean nothing '
            . 'to gtag.js.',
        'google/' => 'The data layer has no setting for it.',
    ];

    private const string PIXEL_CONSTANT_VALUE = '0';
    private const string VALUE_SOURCE_PURCHASE = 'purchase';
    private const string VALUE_SOURCE_FIXED = 'fixed';
    private const string ADS_ID_PREFIX = 'AW-';
    private const string ON = '1';

    private const array SCOPE_ORDER = [
        ScopeConfigInterface::SCOPE_TYPE_DEFAULT => 0,
        'websites' => 1,
        'stores' => 2,
    ];

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @inheritDoc
     */
    public function translate(array $oldRows, array $storedKeys, array $storeWebsites): array
    {
        $stored = [];
        foreach ($oldRows as $row) {
            if (isset(self::FEEDS[$row['path']])) {
                $stored[$this->scopeKey($row['scope'], $row['scope_id'])][$row['path']] = $row['value'];
                continue;
            }
            $this->logger->info('Dropped a setting of Magento\'s Google modules that has no successor.', [
                'path' => $row['path'],
                'scope' => $row['scope'],
                'scope_id' => $row['scope_id'],
                'reason' => $this->dropReason($row['path']),
            ]);
        }

        $scopes = [];
        foreach ($oldRows as $row) {
            if (isset(self::FEEDS[$row['path']])) {
                $scopes[$this->scopeKey($row['scope'], $row['scope_id'])] = [$row['scope'], $row['scope_id']];
            }
        }
        uasort(
            $scopes,
            static fn (array $left, array $right): int
                => [self::SCOPE_ORDER[$left[0]] ?? 3, $left[1]] <=> [self::SCOPE_ORDER[$right[0]] ?? 3, $right[1]]
        );

        $writes = [];
        foreach ($scopes as $key => [$scope, $scopeId]) {
            $chain = $this->chain($scope, $scopeId, $storeWebsites);
            $read = fn (string $path): string => $this->effective($stored, $chain, $path);
            $values = $this->newValues($read, $scope, $scopeId);
            $fed = [];
            foreach (array_keys($stored[$key] ?? []) as $path) {
                foreach (self::FEEDS[$path] as $setting) {
                    $fed[$setting] = true;
                }
            }
            foreach (array_keys($fed) as $setting) {
                $write = $this->write($setting, $values[$setting](), $scope, $scopeId, $storedKeys);
                if ($write !== null) {
                    $writes[] = $write;
                }
            }
        }

        return $writes;
    }

    /**
     * Get how each new setting's value at one scope is worked out from what the old modules used there
     *
     * Each value is worked out only when asked, so an unusable old value is reported only where it would be written.
     *
     * @param \Closure $read Reads an old setting's value at the scope, inheritance applied
     * @param string $scope
     * @param int $scopeId
     * @return array Keyed by new setting; a value is null when the setting means nothing at the scope (a fixed value
     *      where the conversion value is not fixed)
     * @phpstan-param \Closure(string): string $read
     * @phpstan-return array<string, \Closure(): ?string>
     */
    private function newValues(\Closure $read, string $scope, int $scopeId): array
    {
        $gtagOn = $read(self::GTAG_ADS_ACTIVE) === self::ON;
        $pixelOn = $read(self::PIXEL_ACTIVE) === self::ON;
        $fromGtag = $gtagOn || (!$pixelOn && trim($read(self::GTAG_ADS_ID)) !== '');
        $fixed = !$fromGtag && trim($read(self::PIXEL_VALUE_TYPE)) === self::PIXEL_CONSTANT_VALUE;

        return [
            self::MEASUREMENT_ID => fn (): string => $read(self::GA4_ACTIVE) === self::ON
                ? $this->measurementId($read(self::GA4_ID), $scope, $scopeId)
                : '',
            self::ADS_ENABLED => static fn (): string => $gtagOn || $pixelOn ? self::ON : '0',
            self::ADS_CONVERSION_ID => fn (): string => $this->conversionId(
                $read($fromGtag ? self::GTAG_ADS_ID : self::PIXEL_ID),
                $scope,
                $scopeId
            ),
            self::ADS_CONVERSION_LABEL => static fn (): string
                => trim($read($fromGtag ? self::GTAG_ADS_LABEL : self::PIXEL_LABEL)),
            self::ADS_VALUE_SOURCE => static fn (): string
                => $fixed ? self::VALUE_SOURCE_FIXED : self::VALUE_SOURCE_PURCHASE,
            self::ADS_FIXED_VALUE => fn (): ?string
                => $fixed ? $this->amount($read(self::PIXEL_VALUE), $scope, $scopeId) : null,
        ];
    }

    /**
     * Get the row to write for one new setting at one scope, or null when nothing is written
     *
     * @param string $setting The new setting below the data layer's section
     * @param string|null $value Null when the setting means nothing at the scope
     * @param string $scope
     * @param int $scopeId
     * @param array $storedKeys
     * @return array|null
     * @phpstan-param array<string, true> $storedKeys
     * @phpstan-return array{scope: string, scope_id: int, path: string, value: string}|null
     */
    private function write(string $setting, ?string $value, string $scope, int $scopeId, array $storedKeys): ?array
    {
        $path = self::NEW_SECTION . $setting;
        $context = ['path' => $path, 'scope' => $scope, 'scope_id' => $scopeId];
        if ($value === null) {
            return null;
        }
        if (isset($storedKeys[$this->scopeKey($scope, $scopeId) . '|' . $path])) {
            $this->logger->info('Kept the stored data layer setting instead of the Google module\'s value.', $context);

            return null;
        }
        if ($scope === ScopeConfigInterface::SCOPE_TYPE_DEFAULT && $value === self::NEW_DEFAULTS[$setting]) {
            $this->logger->info('Left a data layer setting at its default: the Google module used the same.', $context);

            return null;
        }
        $this->logger->info('Moved a setting of Magento\'s Google modules to the data layer.', $context + [
            'value' => $value,
        ]);

        return ['scope' => $scope, 'scope_id' => $scopeId, 'path' => $path, 'value' => $value];
    }

    /**
     * Read an old setting at a scope as the old module did
     *
     * The nearest scope of the chain that stores it wins, else the old module's default.
     *
     * @param array $stored Old values by scope key and path
     * @param array $chain Scope keys, the scope itself first and the default scope last
     * @param string $path
     * @return string
     * @phpstan-param array<string, array<string, string>> $stored
     * @phpstan-param list<string> $chain
     */
    private function effective(array $stored, array $chain, string $path): string
    {
        foreach ($chain as $key) {
            if (isset($stored[$key][$path])) {
                return $stored[$key][$path];
            }
        }

        return self::OLD_DEFAULTS[$path] ?? '';
    }

    /**
     * Get the scopes a scope inherits from, itself first
     *
     * A store view whose website is unknown inherits from the default scope directly.
     *
     * @param string $scope
     * @param int $scopeId
     * @param array $storeWebsites
     * @return string[]
     * @phpstan-param array<int, int> $storeWebsites
     * @phpstan-return list<string>
     */
    private function chain(string $scope, int $scopeId, array $storeWebsites): array
    {
        $chain = [$this->scopeKey($scope, $scopeId)];
        if ($scope === 'stores' && isset($storeWebsites[$scopeId])) {
            $chain[] = $this->scopeKey('websites', $storeWebsites[$scopeId]);
        }
        $default = $this->scopeKey(ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
        if ($chain[0] !== $default) {
            $chain[] = $default;
        }

        return $chain;
    }

    /**
     * A measurement id as the data layer stores it
     *
     * @param string $value
     * @param string $scope
     * @param int $scopeId
     * @return string Empty when the old value is no measurement id
     */
    private function measurementId(string $value, string $scope, int $scopeId): string
    {
        $measurementId = strtoupper(trim($value));
        if ($measurementId === '' || preg_match(ConfigInterface::MEASUREMENT_ID_PATTERN, $measurementId) === 1) {
            return $measurementId;
        }
        $this->warnUnusable('measurement id', $value, $scope, $scopeId);

        return '';
    }

    /**
     * A Google Ads conversion id as the data layer stores it, with the `AW-` prefix a bare number lacks
     *
     * @param string $value
     * @param string $scope
     * @param int $scopeId
     * @return string Empty when the old value is no conversion id
     */
    private function conversionId(string $value, string $scope, int $scopeId): string
    {
        $conversionId = strtoupper(trim($value));
        if (ctype_digit($conversionId)) {
            $conversionId = self::ADS_ID_PREFIX . $conversionId;
        }
        if ($conversionId === '' || preg_match(ConfigInterface::ADS_CONVERSION_ID_PATTERN, $conversionId) === 1) {
            return $conversionId;
        }
        $this->warnUnusable('Google Ads conversion id', $value, $scope, $scopeId);

        return '';
    }

    /**
     * A fixed conversion value as the data layer stores it
     *
     * @param string $value
     * @param string $scope
     * @param int $scopeId
     * @return string Empty when the old value is not an amount of zero or more
     */
    private function amount(string $value, string $scope, int $scopeId): string
    {
        $amount = trim($value);
        if (is_numeric($amount) && is_finite((float) $amount) && (float) $amount >= 0.0) {
            return $amount;
        }
        $this->warnUnusable('fixed conversion value', $value, $scope, $scopeId);

        return '';
    }

    /**
     * Report an old value the data layer cannot use
     *
     * @param string $what
     * @param string $value
     * @param string $scope
     * @param int $scopeId
     * @return void
     */
    private function warnUnusable(string $what, string $value, string $scope, int $scopeId): void
    {
        $this->logger->warning(
            sprintf('The Google module\'s %s is not one the data layer can use; this scope gets none.', $what),
            ['value' => $value, 'scope' => $scope, 'scope_id' => $scopeId]
        );
    }

    /**
     * Why a dropped old setting has no successor
     *
     * @param string $path
     * @return string
     */
    private function dropReason(string $path): string
    {
        foreach (self::DROP_REASONS as $prefix => $reason) {
            if (str_starts_with($path, $prefix)) {
                return $reason;
            }
        }

        return self::DROP_REASONS['google/'];
    }

    /**
     * Identify one scope
     *
     * @param string $scope
     * @param int $scopeId
     * @return string
     */
    private function scopeKey(string $scope, int $scopeId): string
    {
        return $scope . '|' . $scopeId;
    }
}
