<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Url;

use Hryvinskyi\DataLayer\Model\Url\PathOfUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A link is reduced to its path the way the browser reduces a clicked link: no scheme, host, port, query or
 * fragment, the trailing slash as written
 */
class PathOfUrlTest extends TestCase
{
    /**
     * The normalisation table the browser side shares
     *
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function links(): array
    {
        return [
            'absolute' => ['https://shop.test/laptop-a.html', '/laptop-a.html'],
            'absolute with query and fragment' => ['https://shop.test/laptop-a.html?b=1#reviews', '/laptop-a.html'],
            'plain http with port' => ['http://shop.test:8080/laptops/laptop-b.html', '/laptops/laptop-b.html'],
            'trailing slash kept' => ['https://shop.test/laptops/', '/laptops/'],
            'no trailing slash added' => ['https://shop.test/laptops', '/laptops'],
            'host only' => ['https://shop.test', '/'],
            'upper-case scheme' => ['HTTPS://shop.test/Laptop-A.html', '/Laptop-A.html'],
            'percent-encoding kept' => ['https://shop.test/a%20b.html', '/a%20b.html'],
            'root-relative' => ['/laptop-b.html', '/laptop-b.html'],
            'root-relative with query' => ['/laptop-b.html?color=red', '/laptop-b.html'],
            'protocol-relative' => ['//shop.test/laptop-c.html?x=1', '/laptop-c.html'],
            'surrounding spaces' => ['  https://shop.test/laptop-a.html  ', '/laptop-a.html'],
            'empty' => ['', null],
            'blank' => ['   ', null],
            'fragment only' => ['#top', null],
            'query only' => ['?page=2', null],
            'bare relative path' => ['laptop-a.html', null],
            'mail link' => ['mailto:sales@shop.test', null],
            'script link' => ['javascript:void(0)', null],
            'unparsable' => ['http:///laptop-a.html', null],
        ];
    }

    /**
     * Each link of the table gives its path, or nothing
     *
     * @dataProvider links
     * @param string $url
     * @param string|null $expected
     * @return void
     */
    #[DataProvider('links')]
    public function testPathOfLink(string $url, ?string $expected): void
    {
        self::assertSame($expected, (new PathOfUrl())->pathOf($url));
    }
}
