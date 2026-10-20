<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Data;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Model\Data\Ga4Name;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every published whole-value pattern accepts its value and refuses the same value followed by a line break, which a
 * plain `$` anchor would let through
 */
class AnchoredPatternsTest extends TestCase
{
    /**
     * The pattern matches the value alone and refuses it with a trailing line break
     *
     * @dataProvider patterns
     * @param string $pattern
     * @param string $value A value the pattern accepts
     * @return void
     */
    #[DataProvider('patterns')]
    public function testTrailingLineBreakIsRefused(string $pattern, string $value): void
    {
        self::assertSame(1, preg_match($pattern, $value));
        self::assertSame(0, preg_match($pattern, $value . "\n"));
    }

    /**
     * Each published pattern with a value it accepts
     *
     * @return array<string, array{string, string}>
     */
    public static function patterns(): array
    {
        return [
            'GA4 name' => [Ga4Name::PATTERN, 'add_to_cart'],
            'container id' => [ConfigInterface::CONTAINER_ID_PATTERN, 'GTM-AB12'],
            'measurement id' => [ConfigInterface::MEASUREMENT_ID_PATTERN, 'G-AB12'],
            'conversion id' => [ConfigInterface::ADS_CONVERSION_ID_PATTERN, 'AW-123'],
            'conversion label' => [ConfigInterface::ADS_CONVERSION_LABEL_PATTERN, 'Ab_1-x'],
        ];
    }
}
