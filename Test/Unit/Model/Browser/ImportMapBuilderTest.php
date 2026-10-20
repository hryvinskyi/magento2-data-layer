<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Browser;

use Hryvinskyi\DataLayer\Model\Browser\ImportMapBuilder;
use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\View\Asset\Repository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Each name resolves to the address the asset repository gives its module script, in the given order; an entry that
 * is not a name with a module script's file id fails loudly; the map is written as `{"imports": {...}}` safe inside a
 * script element
 */
class ImportMapBuilderTest extends TestCase
{
    private const STATIC = 'https://static.test/adminhtml/';

    /**
     * Names resolve through the repository, minified file names included, keeping the given order
     *
     * @return void
     */
    public function testNamesResolveThroughTheAssetRepositoryInOrder(): void
    {
        self::assertSame(
            [
                '@vendor/feature/b.js' => self::STATIC . 'Vendor_Module/js/feature/b.min.js',
                '@vendor/feature/a.mjs' => self::STATIC . 'Vendor_Module/js/feature/a.mjs',
            ],
            $this->builder()->addresses([
                '@vendor/feature/b.js' => 'Vendor_Module::js/feature/b.js',
                '@vendor/feature/a.mjs' => 'Vendor_Module::js/feature/a.mjs',
            ])
        );
        self::assertSame([], $this->builder()->addresses([]));
    }

    /**
     * An entry that is not a name with a module script's file id fails, naming the entry
     *
     * @param array<int|string, mixed> $fileIds
     * @return void
     * @dataProvider malformedEntries
     */
    #[DataProvider('malformedEntries')]
    public function testMalformedEntryFails(array $fileIds): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must map to the file id of a module script');

        $this->builder()->addresses($fileIds);
    }

    /**
     * Entries that are not usable
     *
     * @return array<string, array{array<int|string, mixed>}>
     */
    public static function malformedEntries(): array
    {
        return [
            'theme file' => [['@vendor/a.js' => 'js/a.js']],
            'not a script' => [['@vendor/a.css' => 'Vendor_Module::css/a.css']],
            'absolute address' => [['@vendor/a.js' => 'https://cdn.test/a.js']],
            'trailing newline' => [['@vendor/a.js' => "Vendor_Module::js/a.js\n"]],
            'not text' => [['@vendor/a.js' => 42]],
            'no name' => [['Vendor_Module::js/a.js']],
            'blank name' => [[' ' => 'Vendor_Module::js/a.js']],
        ];
    }

    /**
     * The map is an `imports` object with `<` and `>` escaped; no addresses give an empty object, never a list
     *
     * @return void
     */
    public function testMapIsWrittenAsAnImportsObject(): void
    {
        self::assertSame(
            '{"imports":{"@vendor\/a.js":"https:\/\/static.test\/a.js?x=\u003C\/script\u003E"}}',
            $this->builder()->json(['@vendor/a.js' => 'https://static.test/a.js?x=</script>'])
        );
        self::assertSame('{"imports":{}}', $this->builder()->json([]));
    }

    /**
     * Build the builder over a repository that publishes `.js` scripts under their minified names
     *
     * @return ImportMapBuilder
     */
    private function builder(): ImportMapBuilder
    {
        $repository = $this->createMock(Repository::class);
        $repository->method('getUrl')->willReturnCallback(
            static fn (string $fileId): string => self::STATIC
                . str_replace('::', '/', (string) preg_replace('/\.js$/D', '.min.js', $fileId))
        );

        return new ImportMapBuilder($repository, new JsonHexTag());
    }
}
