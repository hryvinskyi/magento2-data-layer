<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Config;

use Hryvinskyi\DataLayer\Model\Config\DraftSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A settings draft is readable only while its work runs, is cleared however the work ends, is never nested and
 * accepts only config paths
 */
class DraftSettingsTest extends TestCase
{
    /**
     * Drafted paths read as drafted while the work runs, the work's result comes back, and nothing is drafted after
     *
     * @return void
     */
    public function testDraftIsReadableOnlyWhileTheWorkRuns(): void
    {
        $draft = new DraftSettings();

        $seen = $draft->withDraft(
            ['hryvinskyi_datalayer/general/enabled' => '1', 'hryvinskyi_datalayer/gtm/container_id' => ''],
            static fn (): array => [
                $draft->has('hryvinskyi_datalayer/general/enabled'),
                $draft->value('hryvinskyi_datalayer/general/enabled'),
                $draft->has('hryvinskyi_datalayer/gtm/container_id'),
                $draft->value('hryvinskyi_datalayer/gtm/container_id'),
                $draft->has('hryvinskyi_datalayer/gtm/script_host'),
            ]
        );

        self::assertSame([true, '1', true, '', false], $seen);
        self::assertFalse($draft->has('hryvinskyi_datalayer/general/enabled'));
    }

    /**
     * Work that throws still clears the draft, and its exception reaches the caller
     *
     * @return void
     */
    public function testDraftIsClearedWhenTheWorkThrows(): void
    {
        $draft = new DraftSettings();

        try {
            $draft->withDraft(['hryvinskyi_datalayer/general/enabled' => '1'], $this->failingWork());
            self::fail('The work\'s exception must reach the caller.');
        } catch (\RuntimeException $exception) {
            self::assertSame('work failed', $exception->getMessage());
        }

        self::assertFalse($draft->has('hryvinskyi_datalayer/general/enabled'));
        self::assertTrue(
            $draft->withDraft(
                ['hryvinskyi_datalayer/general/enabled' => '0'],
                static fn (): bool => $draft->has('hryvinskyi_datalayer/general/enabled')
            ),
            'A new draft opens afterwards.'
        );
    }

    /**
     * A draft opened inside another is refused, and the outer draft stays readable and is cleared as usual
     *
     * @return void
     */
    public function testNestedDraftIsRefused(): void
    {
        $draft = new DraftSettings();

        $outcome = $draft->withDraft(
            ['hryvinskyi_datalayer/general/enabled' => '1'],
            static function () use ($draft): string {
                try {
                    $draft->withDraft(['hryvinskyi_datalayer/general/enabled' => '0'], static fn (): string => '0');
                } catch (\LogicException) {
                    return $draft->value('hryvinskyi_datalayer/general/enabled');
                }

                return 'nested draft opened';
            }
        );

        self::assertSame('1', $outcome);
        self::assertFalse($draft->has('hryvinskyi_datalayer/general/enabled'));
    }

    /**
     * A path that is not a section/group/field config path is refused, and no work runs
     *
     * @dataProvider unusablePaths
     * @param string $path
     * @return void
     */
    #[DataProvider('unusablePaths')]
    public function testUnusablePathIsRefused(string $path): void
    {
        $draft = new DraftSettings();
        $ran = false;

        try {
            $draft->withDraft([$path => '1'], static function () use (&$ran): void {
                $ran = true;
            });
            self::fail(sprintf('"%s" must be refused.', $path));
        } catch (\InvalidArgumentException) {
            self::assertFalse($ran);
            self::assertFalse($draft->has($path));
        }
    }

    /**
     * Paths that are not config paths
     *
     * @return array<string, array{string}>
     */
    public static function unusablePaths(): array
    {
        return [
            'empty' => [''],
            'one segment' => ['hryvinskyi_datalayer'],
            'two segments' => ['hryvinskyi_datalayer/general'],
            'empty segment' => ['hryvinskyi_datalayer//enabled'],
            'trailing slash' => ['hryvinskyi_datalayer/general/enabled/'],
            'leading slash' => ['/hryvinskyi_datalayer/general/enabled'],
            'whitespace' => ['hryvinskyi_datalayer/general/en abled'],
        ];
    }

    /**
     * Reading a path that is not drafted fails loudly, inside and outside a draft
     *
     * @return void
     */
    public function testReadingAnUndraftedPathFails(): void
    {
        $draft = new DraftSettings();

        $draft->withDraft(['hryvinskyi_datalayer/general/enabled' => '1'], function () use ($draft): void {
            $this->expectException(\OutOfBoundsException::class);
            $draft->value('hryvinskyi_datalayer/gtm/container_id');
        });
    }

    /**
     * Outside any draft, every read is refused
     *
     * @return void
     */
    public function testReadingWithoutADraftFails(): void
    {
        $this->expectException(\OutOfBoundsException::class);

        (new DraftSettings())->value('hryvinskyi_datalayer/general/enabled');
    }

    /**
     * Work that fails
     *
     * @return \Closure(): string
     */
    private function failingWork(): \Closure
    {
        return static function (): string {
            throw new \RuntimeException('work failed');
        };
    }
}
