<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Browser;

use Hryvinskyi\DataLayer\Api\Browser\ListScriptMarkupInterface;

/**
 * Prints a data script as `<script type="application/json" data-hryvinskyi-datalayer="{kind}">{json}</script>`, and a
 * block with list data as an empty start marker (`data-hryvinskyi-datalayer="list-start"`), the block's HTML and its
 * data scripts. The marker and each of those scripts carry `data-hryvinskyi-datalayer-list` with the same id: the
 * first 16 hexadecimal digits of the SHA-1 of the data scripts. The id comes first among the attributes, so the
 * scripts keep their usual opening text after it.
 */
class ListScriptMarkup implements ListScriptMarkupInterface
{
    private const OPENING = '<script type="application/json" data-hryvinskyi-datalayer="';
    private const PAIR_ATTRIBUTE = 'data-hryvinskyi-datalayer-list';
    private const START_KIND = 'list-start';
    private const KIND = '~^[a-z]+(?:-[a-z]+)*$~D';
    private const PAIR_LENGTH = 16;

    /**
     * @inheritDoc
     */
    public function dataScript(string $kind, string $json): string
    {
        if (preg_match(self::KIND, $kind) !== 1) {
            throw new \InvalidArgumentException(sprintf(
                'A data layer data script describes "%s"; a kind is lower-case letters and hyphens.',
                $kind
            ));
        }

        return self::OPENING . $kind . '">' . $json . '</script>';
    }

    /**
     * @inheritDoc
     */
    public function around(string $html, string $dataScripts): string
    {
        if ($dataScripts === '') {
            return $html;
        }
        $paired = '<script ' . self::PAIR_ATTRIBUTE . '="' . substr(sha1($dataScripts), 0, self::PAIR_LENGTH) . '" '
            . substr(self::OPENING, strlen('<script '));

        return str_replace(self::OPENING, $paired, $this->dataScript(self::START_KIND, ''))
            . $html
            . str_replace(self::OPENING, $paired, $dataScripts);
    }
}
