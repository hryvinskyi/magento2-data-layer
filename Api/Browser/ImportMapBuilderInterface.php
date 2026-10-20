<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Browser;

/**
 * Builds the import map that ES modules resolve their named imports through, in any area
 *
 * Browser modules import one another by name rather than by relative file name, so each published file can carry
 * whatever name the static content gives it (a minified copy is published as `.min.js`). A name (a specifier such as
 * `@vendor/feature/file.js`) stands for a module script's file id (`Vendor_Module::js/file.js`), and its address is
 * the one the asset repository gives that file for the current design area, theme and locale.
 */
interface ImportMapBuilderInterface
{
    /**
     * Resolve named module scripts to their static addresses
     *
     * @param array<int|string,mixed> $fileIds File ids keyed by the name modules import them by
     * @return array<string, string> The addresses keyed by the same names, in the given order
     * @throws \InvalidArgumentException When a name is blank or a value is not the file id of a module script
     * @throws \Magento\Framework\Exception\LocalizedException When a file cannot be resolved
     */
    public function addresses(array $fileIds): array;

    /**
     * Write addresses as an import map: `{"imports": {name: address}}`, an empty object when there are none
     *
     * `<` and `>` are escaped, so the map can be printed inside a script element as it is.
     *
     * @param array<string,string> $addresses Keyed by name
     * @return string
     * @throws \InvalidArgumentException When the map cannot be written as JSON
     */
    public function json(array $addresses): string;
}
