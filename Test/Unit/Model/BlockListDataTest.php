<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Model\BlockListData;
use Hryvinskyi\DataLayer\Model\Browser\ListScriptMarkup;
use Hryvinskyi\DataLayer\Test\Unit\Model\Catalog\FakeCache;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\View\Element\AbstractBlock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * List data of a block-cached block: cached beside the block's entry with the block's tags and lifetime, rendered
 * only on a miss, and always rendered for a block that is not block-cached
 */
class BlockListDataTest extends TestCase
{
    private const SCRIPT = '<script type="application/json" data-hryvinskyi-datalayer="list">{}</script>';

    /**
     * @var FakeCache
     */
    private FakeCache $cache;

    /**
     * @var bool
     */
    private bool $blockCacheEnabled = true;

    /**
     * @var int
     */
    private int $renders = 0;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->cache = new FakeCache($this->createMock(FrontendInterface::class));
    }

    /**
     * A miss renders and stores the data under the block's key, tags and lifetime; a hit returns it without rendering
     *
     * @return void
     */
    public function testMissRendersAndStoresThenHitDoesNotRender(): void
    {
        $data = ['cache_lifetime' => 86400, 'cache_tags' => ['cat_p']];
        $block = $this->block($data, 'BLOCK_abc', ['cat_p_1', 'cat_p_2']);

        $first = $this->blockListData()->append($block, '<ol></ol>', $this->render());
        $second = $this->blockListData()->append($block, '<ol></ol>', $this->render());

        self::assertSame($this->wrapped(), $first);
        self::assertSame($this->wrapped(), $second);

        self::assertSame(1, $this->renders);
        $key = 'hryvinskyi_datalayer_list_' . sha1('BLOCK_abc');
        self::assertSame(
            [
                $key => [
                    'data' => self::SCRIPT,
                    'tags' => [
                        'block_html',
                        'cat_p',
                        'cat_p_1',
                        'cat_p_2',
                        'hryvinskyi_datalayer',
                        'hryvinskyi_datalayer_category_tree',
                    ],
                    'lifetime' => 86400,
                ],
            ],
            $this->cache->entries
        );
    }

    /**
     * A block without identities is tagged with the block cache group, its cache tags, the data layer's tag and the
     * category tree's tag
     *
     * @return void
     */
    public function testBlockWithoutIdentitiesKeepsItsOwnTags(): void
    {
        $block = $this->createMock(AbstractBlock::class);
        $block->method('getData')->willReturnCallback(
            static fn (string $key = ''): mixed => ['cache_lifetime' => '3600', 'cache_tags' => ['cms_b']][$key] ?? null
        );
        $block->method('getCacheKey')->willReturn('BLOCK_def');

        $this->blockListData()->append($block, '<div></div>', $this->render());

        $entry = $this->cache->entries['hryvinskyi_datalayer_list_' . sha1('BLOCK_def')] ?? null;
        self::assertNotNull($entry);
        self::assertSame(
            ['block_html', 'cms_b', 'hryvinskyi_datalayer', 'hryvinskyi_datalayer_category_tree'],
            $entry['tags']
        );
        self::assertSame(3600, $entry['lifetime']);
    }

    /**
     * Blocks that are not block-cached always render and store nothing
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function uncachedLifetimes(): array
    {
        return [
            'no lifetime' => [[]],
            'null lifetime' => [['cache_lifetime' => null]],
            'false lifetime' => [['cache_lifetime' => false]],
            'zero lifetime' => [['cache_lifetime' => 0]],
            'negative lifetime' => [['cache_lifetime' => -1]],
            'text lifetime' => [['cache_lifetime' => 'forever']],
        ];
    }

    /**
     * A block without a positive cache lifetime renders every time
     *
     * @dataProvider uncachedLifetimes
     * @param array<string, mixed> $data
     * @return void
     */
    #[DataProvider('uncachedLifetimes')]
    public function testUncachedBlockAlwaysRenders(array $data): void
    {
        $block = $this->block($data, 'BLOCK_abc', []);

        $this->blockListData()->append($block, '<ol></ol>', $this->render());
        $html = $this->blockListData()->append($block, '<ol></ol>', $this->render());

        self::assertSame($this->wrapped(), $html);
        self::assertSame(2, $this->renders);
        self::assertSame([], $this->cache->entries);
    }

    /**
     * With the block cache type disabled the data is never cached
     *
     * @return void
     */
    public function testDisabledBlockCacheTypeAlwaysRenders(): void
    {
        $this->blockCacheEnabled = false;
        $block = $this->block(['cache_lifetime' => 86400], 'BLOCK_abc', []);

        $this->blockListData()->append($block, '<ol></ol>', $this->render());
        $this->blockListData()->append($block, '<ol></ol>', $this->render());

        self::assertSame(2, $this->renders);
        self::assertSame([], $this->cache->entries);
        self::assertSame(0, $this->cache->loads);
    }

    /**
     * A block whose custom cache key is invalid is treated as uncached
     *
     * @return void
     */
    public function testInvalidCustomCacheKeyIsTreatedAsUncached(): void
    {
        $block = $this->createMock(AbstractBlock::class);
        $block->method('getData')->willReturnCallback(
            static fn (string $key = ''): mixed => $key === 'cache_lifetime' ? 86400 : null
        );
        $block->method('getCacheKey')->willThrowException(new RuntimeException(__('Invalid cache key.')));

        self::assertSame(
            $this->wrapped(),
            $this->blockListData()->append($block, '<ol></ol>', $this->render())
        );
        self::assertSame(1, $this->renders);
        self::assertSame([], $this->cache->entries);
    }

    /**
     * Empty data is not stored, so the next render is asked again; without data no start marker is printed
     *
     * @return void
     */
    public function testEmptyDataIsNotStored(): void
    {
        $block = $this->block(['cache_lifetime' => 86400], 'BLOCK_abc', []);
        $render = function (): string {
            $this->renders++;

            return '';
        };

        self::assertSame('<ol></ol>', $this->blockListData()->append($block, '<ol></ol>', $render));
        $this->blockListData()->append($block, '<ol></ol>', $render);

        self::assertSame(2, $this->renders);
        self::assertSame([], $this->cache->entries);
    }

    /**
     * Build the service over the fake cache and the test's cache state
     *
     * @return BlockListData
     */
    private function blockListData(): BlockListData
    {
        $state = $this->createMock(StateInterface::class);
        $state->method('isEnabled')->willReturnCallback(
            fn (string $type): bool => $type === 'block_html' && $this->blockCacheEnabled
        );

        return new BlockListData($this->cache, $state, new ListScriptMarkup());
    }

    /**
     * The block's HTML with the start marker before it and the data after it, as the markup writes them
     *
     * @return string
     */
    private function wrapped(): string
    {
        return (new ListScriptMarkup())->around('<ol></ol>', self::SCRIPT);
    }

    /**
     * A render callback that counts its calls
     *
     * @return callable(): string
     */
    private function render(): callable
    {
        return function (): string {
            $this->renders++;

            return self::SCRIPT;
        };
    }

    /**
     * A block that exposes identities
     *
     * @param array<string, mixed> $data
     * @param string $cacheKey
     * @param list<string> $identities
     * @return IdentityBlock
     */
    private function block(array $data, string $cacheKey, array $identities): IdentityBlock
    {
        $block = $this->createMock(IdentityBlock::class);
        $block->method('getData')->willReturnCallback(
            static fn (string $key = ''): mixed => $data[$key] ?? null
        );
        $block->method('getCacheKey')->willReturn($cacheKey);
        $block->method('getIdentities')->willReturn($identities);

        return $block;
    }
}
