<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Url;

/**
 * Reduces a link to the path the browser compares when it recognises a click on a listed product or promotion
 *
 * The browser normalises the clicked link the same way, so both sides must agree: no scheme, host, port, query or
 * fragment; the path as written, trailing slash included.
 */
interface PathOfUrlInterface
{
    /**
     * Get the path of a link
     *
     * @param string $url An absolute http(s) URL, a protocol-relative URL or a path starting with "/"
     * @return string|null The path ("/" for a URL that has none); null for an empty link, a link of another scheme,
     *     a relative path that only a page could resolve, or a link that cannot be parsed
     */
    public function pathOf(string $url): ?string;
}
