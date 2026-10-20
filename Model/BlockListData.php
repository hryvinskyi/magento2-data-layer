<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\Browser\ListScriptMarkupInterface;
use Hryvinskyi\DataLayer\Model\Catalog\CachedCategoryTreeSnapshot;
use Hryvinskyi\DataLayerApi\Api\BlockListDataInterface;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\AbstractBlock;

/**
 * Keeps the list data of a block-cached block in a cache entry of its own, beside the block's
 *
 * A block saves its cached HTML before after-plugins run, so list data appended by one is not part of a cache hit.
 * The block's own cache lifetime and tags are protected, so they are mirrored from what the block makes public:
 * - the data is cached only while the block cache type is enabled and the block's `cache_lifetime` data is a
 *   positive number of seconds, which is then the entry's lifetime;
 * - the key is derived from the block's `getCacheKey()`; a block whose key cannot be computed (an invalid custom
 *   `cache_key`) is treated as uncached;
 * - the tags are the block cache group, the block's `cache_tags` data, its identities when it exposes them, and the
 *   data layer's own tag: the same set as the block's own entry, so a product save or a clean of the block cache
 *   type removes both together, and a change of the data layer's settings removes the data alone;
 * - the data also carries the category tree's tag: its items name their categories, so a category that is renamed,
 *   moved or deleted removes the data, which is rebuilt with the new names on the next render.
 * Empty data (nothing to report) is not stored: an empty entry could not be told from a miss.
 *
 * Whenever there is data, an empty start marker is printed right before the block's HTML, paired with the block's data
 * scripts by an id, so the browser knows where the block's elements begin: it never takes the elements before them for
 * the list's, and a list printed inside the block does not cut the block's own list short. Without data the HTML is
 * returned as it is.
 *
 * Known limit: the block's entry and this one are separate records. When the cache evicts only one of them, fresh
 * HTML can be paired with older list data, or the reverse, until the next clean of a shared tag.
 */
class BlockListData implements BlockListDataInterface
{
    private const KEY_PREFIX = 'hryvinskyi_datalayer_list_';

    /**
     * @param CacheInterface $cache The application cache the block itself writes to
     * @param StateInterface $cacheState
     * @param ListScriptMarkupInterface $markup Prints the start marker and pairs it with the data scripts
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly StateInterface $cacheState,
        private readonly ListScriptMarkupInterface $markup
    ) {
    }

    /**
     * @inheritDoc
     */
    public function append(AbstractBlock $block, string $html, callable $render): string
    {
        $lifetime = $this->lifetime($block);
        $key = $lifetime === null ? null : $this->key($block);
        if ($lifetime === null || $key === null) {
            return $this->markup->around($html, $render());
        }

        $cached = (string) $this->cache->load($key);
        if ($cached !== '') {
            return $this->markup->around($html, $cached);
        }

        $data = $render();
        if ($data !== '') {
            $this->cache->save($data, $key, $this->tags($block), $lifetime);
        }

        return $this->markup->around($html, $data);
    }

    /**
     * Get the block's cache lifetime, or null when its HTML is not block-cached
     *
     * @param AbstractBlock $block
     * @return int|null
     */
    private function lifetime(AbstractBlock $block): ?int
    {
        if (!$this->cacheState->isEnabled(AbstractBlock::CACHE_GROUP)) {
            return null;
        }
        $lifetime = $block->getData('cache_lifetime');
        if (!is_int($lifetime) && !(is_string($lifetime) && ctype_digit($lifetime))) {
            return null;
        }

        return (int) $lifetime > 0 ? (int) $lifetime : null;
    }

    /**
     * Derive this entry's key from the block's cache key
     *
     * @param AbstractBlock $block
     * @return string|null Null when the block's cache key cannot be computed
     */
    private function key(AbstractBlock $block): ?string
    {
        try {
            $blockKey = $block->getCacheKey();
        } catch (LocalizedException) {
            return null;
        }

        return self::KEY_PREFIX . sha1((string) $blockKey);
    }

    /**
     * Rebuild the tags the block's own entry carries, plus the data layer's and the category tree's tags
     *
     * @param AbstractBlock $block
     * @return list<string>
     */
    private function tags(AbstractBlock $block): array
    {
        $tags = [AbstractBlock::CACHE_GROUP];
        $blockTags = $block->getData('cache_tags');
        if (is_array($blockTags)) {
            $tags = array_merge($tags, $blockTags);
        }
        if ($block instanceof IdentityInterface) {
            $tags = array_merge($tags, $block->getIdentities());
        }
        $tags[] = CachedCategoryTreeSnapshot::MODULE_CACHE_TAG;
        $tags[] = CachedCategoryTreeSnapshot::CACHE_TAG;

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $tag): string => is_scalar($tag) ? (string) $tag : '', $tags),
            static fn (string $tag): bool => $tag !== ''
        )));
    }
}
