<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Browser;

/**
 * Writes the markup the browser module reads a list's data from: the JSON data scripts, and the start marker that,
 * with them, tells which elements of the page a list occupies
 */
interface ListScriptMarkupInterface
{
    /**
     * Wrap JSON in a data script of the given kind
     *
     * @param string $kind What the script describes, such as `list` or `promotions`: lower-case letters and hyphens
     * @param string $json JSON already safe to print inside a script element (`<` and `>` escaped)
     * @return string
     * @throws \InvalidArgumentException When the kind is not lower-case letters and hyphens
     */
    public function dataScript(string $kind, string $json): string;

    /**
     * Surround a block's HTML with its list's start marker and data scripts, the marker and the scripts paired by an id
     *
     * The id is derived from the data scripts, so a block served with the same data carries the same id, and a list
     * printed inside another list's block keeps an id of its own: the browser scopes each list by its own marker.
     *
     * @param string $html The block's HTML
     * @param string $dataScripts The block's data scripts, as `dataScript()` wrote them; empty when there is no data
     * @return string The HTML unchanged when there is no data
     */
    public function around(string $html, string $dataScripts): string;
}
