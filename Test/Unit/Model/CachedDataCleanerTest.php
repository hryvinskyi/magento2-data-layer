<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Api\Catalog\CategoryTreeInvalidatorInterface;
use Hryvinskyi\DataLayer\Model\CachedDataCleaner;
use Hryvinskyi\DataLayer\Test\Unit\Model\Catalog\FakeCache;
use Magento\Framework\Cache\FrontendInterface;
use PHPUnit\Framework\TestCase;

/**
 * Cleaning drops every entry tagged by the data layer and the trees decoded in the process, and nothing else
 */
class CachedDataCleanerTest extends TestCase
{
    /**
     * List data and trees go, another module's entry stays, and the in-process trees are emptied once
     *
     * @return void
     */
    public function testDropsEverythingTheDataLayerCached(): void
    {
        $cache = new FakeCache($this->createMock(FrontendInterface::class));
        $cache->save('list', 'list', ['block_html', 'hryvinskyi_datalayer']);
        $cache->save('tree', 'tree', ['hryvinskyi_datalayer_category_tree', 'hryvinskyi_datalayer']);
        $cache->save('other', 'other', ['block_html']);
        $trees = $this->createMock(CategoryTreeInvalidatorInterface::class);
        $trees->expects(self::once())->method('invalidate');

        (new CachedDataCleaner($cache, $trees))->clean();

        self::assertSame([['hryvinskyi_datalayer']], $cache->cleanedTags);
        self::assertSame(['other'], array_keys($cache->entries));
    }
}
