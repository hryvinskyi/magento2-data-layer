<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Url;

use Hryvinskyi\DataLayer\Api\Url\PathOfUrlInterface;

/**
 * Takes the path out of a link without resolving anything: the server has no page to resolve a relative link against
 *
 * The link is split with the generic URI pattern of RFC 3986 (appendix B), which accepts any string, so nothing here
 * can fail on a malformed link: a link the browser could not resolve to a page of the shop gives no path.
 */
class PathOfUrl implements PathOfUrlInterface
{
    /**
     * Groups: 2 scheme, 3 authority marker ("//" plus authority), 4 authority, 5 path
     */
    private const URI_PATTERN = '~^(([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\?[^#]*)?(#.*)?$~sD';

    private const WEB_SCHEMES = ['http', 'https'];

    /**
     * @inheritDoc
     */
    public function pathOf(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || preg_match(self::URI_PATTERN, $url, $parts) !== 1) {
            return null;
        }

        $scheme = $parts[2];
        $hasAuthority = $parts[3] !== '';
        $host = $parts[4];
        $path = $parts[5];
        if ($scheme !== '' && (!in_array(strtolower($scheme), self::WEB_SCHEMES, true) || $host === '')) {
            return null;
        }
        if ($hasAuthority) {
            return $host === '' ? null : ($path === '' ? '/' : $path);
        }

        return str_starts_with($path, '/') ? $path : null;
    }
}
