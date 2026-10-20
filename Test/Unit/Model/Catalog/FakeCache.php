<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Catalog;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Cache\FrontendInterface;

/**
 * An application cache held in an array, cleaned by tag the way the real one is
 */
class FakeCache implements CacheInterface
{
    /**
     * @var array<string, array{data: string, tags: list<string>, lifetime: int|null}>
     */
    public array $entries = [];

    /**
     * @var list<list<string>>
     */
    public array $cleanedTags = [];

    /**
     * @var int
     */
    public int $loads = 0;

    /**
     * @param FrontendInterface $frontend
     */
    public function __construct(private readonly FrontendInterface $frontend)
    {
    }

    /**
     * @inheritDoc
     */
    public function getFrontend()
    {
        return $this->frontend;
    }

    /**
     * @inheritDoc
     */
    public function load($identifier)
    {
        $this->loads++;

        return isset($this->entries[$identifier]) ? $this->entries[$identifier]['data'] : '';
    }

    /**
     * @inheritDoc
     *
     * @param string $data
     * @param string $identifier
     * @param array<string> $tags
     * @param int|null $lifeTime
     */
    public function save($data, $identifier, $tags = [], $lifeTime = null)
    {
        $this->entries[$identifier] = ['data' => $data, 'tags' => array_values($tags), 'lifetime' => $lifeTime];

        return true;
    }

    /**
     * @inheritDoc
     */
    public function remove($identifier)
    {
        unset($this->entries[$identifier]);

        return true;
    }

    /**
     * @inheritDoc
     *
     * @param array<string> $tags
     */
    public function clean($tags = [])
    {
        $tags = array_values($tags);
        $this->cleanedTags[] = $tags;
        foreach ($this->entries as $identifier => $entry) {
            if ($tags === [] || array_intersect($tags, $entry['tags']) !== []) {
                unset($this->entries[$identifier]);
            }
        }

        return true;
    }
}
