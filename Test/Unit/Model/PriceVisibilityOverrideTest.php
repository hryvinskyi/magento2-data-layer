<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Model\PriceVisibilityOverride;
use PHPUnit\Framework\TestCase;

/**
 * A forced price-visibility answer holds only while its work runs, is cleared however the work ends and is never
 * nested
 */
class PriceVisibilityOverrideTest extends TestCase
{
    /**
     * Either answer is forced while the work runs, the work's result comes back, and nothing is forced after
     *
     * @return void
     */
    public function testAnswerIsForcedOnlyWhileTheWorkRuns(): void
    {
        $override = new PriceVisibilityOverride();

        self::assertNull($override->forced());
        self::assertTrue($override->withAnswer(true, static fn (): ?bool => $override->forced()));
        self::assertNull($override->forced());
        self::assertFalse($override->withAnswer(false, static fn (): ?bool => $override->forced()));
        self::assertNull($override->forced());
    }

    /**
     * Work that throws still clears the answer, and its exception reaches the caller
     *
     * @return void
     */
    public function testAnswerIsClearedWhenTheWorkThrows(): void
    {
        $override = new PriceVisibilityOverride();

        try {
            $override->withAnswer(false, $this->failingWork());
            self::fail('The work\'s exception must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('work failed', $exception->getMessage());
        }

        self::assertNull($override->forced());
    }

    /**
     * An override opened inside another is refused, and the outer answer holds until its work ends
     *
     * @return void
     */
    public function testNestedOverrideIsRefused(): void
    {
        $override = new PriceVisibilityOverride();

        $outcome = $override->withAnswer(false, static function () use ($override): ?bool {
            try {
                $override->withAnswer(true, static fn (): string => 'nested');
            } catch (\LogicException) {
                return $override->forced();
            }

            return true;
        });

        self::assertFalse($outcome);
        self::assertNull($override->forced());
    }

    /**
     * Work that fails
     *
     * @return \Closure(): bool
     */
    private function failingWork(): \Closure
    {
        return static function (): bool {
            throw new \RuntimeException('work failed');
        };
    }
}
