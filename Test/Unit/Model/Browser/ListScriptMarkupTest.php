<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Browser;

use Hryvinskyi\DataLayer\Model\Browser\ListScriptMarkup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A block with list data is printed as its start marker, its HTML and its data scripts, the marker and the scripts
 * paired by an id of their own; a list printed inside the block keeps its own pairing
 */
class ListScriptMarkupTest extends TestCase
{
    private const PAIR = 'data-hryvinskyi-datalayer-list';

    /**
     * A data script is JSON in a script the browser reads by its kind
     *
     * @return void
     */
    public function testDataScriptCarriesItsKindAndJson(): void
    {
        $elements = $this->elements((new ListScriptMarkup())->dataScript('promotions', '{"a":1}'));

        self::assertSame([['script', 'application/json', 'promotions', null, '{"a":1}']], $elements);
    }

    /**
     * The marker comes before the HTML, the scripts after it, all with one id that the block's HTML does not get
     *
     * @return void
     */
    public function testMarkerAndScriptsArePairedAroundTheBlock(): void
    {
        $markup = new ListScriptMarkup();
        $scripts = $markup->dataScript('list', '{"n":1}') . $markup->dataScript('list', '{"n":2}');

        $elements = $this->elements($markup->around('<ol><li>one</li></ol>', $scripts));

        self::assertCount(4, $elements);
        [$start, $html, $first, $second] = $elements;
        self::assertSame(['script', 'application/json', 'list-start'], array_slice($start, 0, 3));
        self::assertSame('', $start[4], 'The marker is empty.');
        self::assertSame('ol', $html[0]);
        self::assertNull($html[3]);
        self::assertSame(['list', '{"n":1}'], [$first[2], $first[4]]);
        self::assertSame(['list', '{"n":2}'], [$second[2], $second[4]]);
        self::assertIsString($start[3]);
        self::assertMatchesRegularExpression('~^[0-9a-f]{16}$~D', $start[3]);
        self::assertSame($start[3], $first[3]);
        self::assertSame($start[3], $second[3]);
    }

    /**
     * A list inside the block keeps its own id, and the outer list gets another one
     *
     * @return void
     */
    public function testInnerListKeepsItsOwnPairing(): void
    {
        $markup = new ListScriptMarkup();
        $inner = $markup->around('<ul></ul>', $markup->dataScript('list', '{"inner":true}'));

        $elements = $this->elements(
            $markup->around('<h2>Title</h2>' . $inner, $markup->dataScript('list', '{"outer":true}'))
        );

        self::assertSame(
            ['list-start', null, 'list-start', null, 'list', 'list'],
            array_map(static fn (array $element): ?string => $element[2], $elements)
        );
        self::assertSame($elements[0][3], $elements[5][3], 'The outer marker and the outer data are paired.');
        self::assertSame($elements[2][3], $elements[4][3], 'The inner marker and the inner data are paired.');
        self::assertNotSame($elements[0][3], $elements[2][3]);
    }

    /**
     * The same data gives the same id, so a block served from a cache is paired as when it was rendered
     *
     * @return void
     */
    public function testSameDataGivesTheSameId(): void
    {
        $markup = new ListScriptMarkup();
        $scripts = $markup->dataScript('list', '{}');

        self::assertSame($markup->around('<p></p>', $scripts), $markup->around('<p></p>', $scripts));
    }

    /**
     * Without data the HTML is returned as it is
     *
     * @return void
     */
    public function testNoDataLeavesTheHtmlAlone(): void
    {
        self::assertSame('<ol></ol>', (new ListScriptMarkup())->around('<ol></ol>', ''));
    }

    /**
     * A kind that is not lower-case letters and hyphens fails loudly
     *
     * @dataProvider invalidKinds
     * @param string $kind
     * @return void
     */
    #[DataProvider('invalidKinds')]
    public function testInvalidKindFails(string $kind): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ListScriptMarkup())->dataScript($kind, '{}');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKinds(): array
    {
        return [
            'empty' => [''],
            'quote' => ['list" onload="x'],
            'upper case' => ['List'],
            'trailing newline' => ["list\n"],
            'trailing hyphen' => ['list-'],
        ];
    }

    /**
     * Describe the top-level elements of an HTML fragment: tag, type, kind, pairing id and text
     *
     * @param string $html
     * @return list<array{string, ?string, ?string, ?string, string}>
     */
    private function elements(string $html): array
    {
        $document = new \DOMDocument();
        $document->loadHTML('<div id="root">' . $html . '</div>', LIBXML_NOERROR | LIBXML_HTML_NODEFDTD);
        $root = $document->getElementById('root');
        self::assertNotNull($root);
        $elements = [];
        foreach ($root->childNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $elements[] = [
                $node->tagName,
                $this->attribute($node, 'type'),
                $this->attribute($node, 'data-hryvinskyi-datalayer'),
                $this->attribute($node, self::PAIR),
                $node->textContent,
            ];
        }

        return $elements;
    }

    /**
     * Read an attribute, or nothing when the element lacks it
     *
     * @param \DOMElement $element
     * @param string $name
     * @return string|null
     */
    private function attribute(\DOMElement $element, string $name): ?string
    {
        return $element->hasAttribute($name) ? $element->getAttribute($name) : null;
    }
}
