<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Api\Data\EventPresetInterface;
use Hryvinskyi\DataLayer\Model\Data\EventPreset;
use Hryvinskyi\DataLayer\Model\EventPresets;
use Hryvinskyi\DataLayer\Model\KnownEvents;
use Hryvinskyi\DataLayer\Test\Unit\DictionaryRenderer;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\RendererInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Presets select their own events and those of the preset they extend, known events only, in the known events'
 * order; they come sorted, a preset left without events is dropped, and a registration that would silently change a
 * selection is refused when the list is built
 */
class EventPresetsTest extends TestCase
{
    /**
     * Known events in their registration order
     */
    private const KNOWN = ['view_item_list', 'view_item', 'add_to_cart', 'purchase', 'sign_up', 'login'];

    /**
     * The renderer phrases had before the test
     *
     * @var RendererInterface|null
     */
    private ?RendererInterface $renderer = null;

    /**
     * Remember the renderer phrases have before the test
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->renderer = Phrase::getRenderer();
    }

    /**
     * Restore the renderer phrases had before the test
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->renderer !== null) {
            Phrase::setRenderer($this->renderer);
        }
    }

    /**
     * A preset's label and description are kept as registered and translated each time they are read, so the same
     * shared list answers in whichever locale reads it; the key and the icon are never translated
     *
     * @return void
     */
    public function testLabelAndDescriptionAreTranslatedWhenRead(): void
    {
        $preset = $this->presets([
            'lean' => [
                'label' => 'Lean',
                'description' => 'Purchases only',
                'icon' => 'bolt',
                'events' => ['purchase' => 'purchase'],
            ],
        ])->getPresets()[0];

        Phrase::setRenderer(new DictionaryRenderer(['Lean' => 'Mager', 'Purchases only' => 'Nur Käufe']));
        self::assertSame(['lean', 'Mager', 'Nur Käufe', 'bolt'], [
            $preset->getKey(),
            $preset->getLabel(),
            $preset->getDescription(),
            $preset->getIcon(),
        ]);

        Phrase::setRenderer(new DictionaryRenderer(['Lean' => 'Mince', 'Purchases only' => 'Achats seulement']));
        self::assertSame(['Mince', 'Achats seulement'], [$preset->getLabel(), $preset->getDescription()]);
    }

    /**
     * A preset extending another selects both sets once each, in the known events' order; unknown events are left out
     *
     * @return void
     */
    public function testExtendedPresetSelectsBothSetsInCatalogueOrder(): void
    {
        $presets = $this->presets([
            'base' => [
                'label' => 'Base',
                'events' => ['purchase' => 'purchase', 'view_item' => 'view_item', 'made_up' => 'made_up'],
            ],
            'more' => [
                'label' => 'More',
                'description' => ' Base plus sign-ups. ',
                'sortOrder' => '5',
                'icon' => 'person_add',
                'extends' => 'base',
                'events' => ['sign_up' => 'sign_up', 'view_item' => 'view_item'],
            ],
        ]);

        self::assertSame(
            [
                ['base', 'Base', '', 0, null, ['view_item', 'purchase']],
                ['more', 'More', 'Base plus sign-ups.', 5, 'person_add', ['view_item', 'purchase', 'sign_up']],
            ],
            $this->described($presets->getPresets())
        );
    }

    /**
     * An extends chain is followed to its end
     *
     * @return void
     */
    public function testExtendsChainIsFollowed(): void
    {
        $presets = $this->presets([
            'a' => ['label' => 'A', 'sortOrder' => 1, 'events' => ['view_item_list' => 'view_item_list']],
            'b' => ['label' => 'B', 'sortOrder' => 2, 'extends' => 'a', 'events' => ['add_to_cart' => 'add_to_cart']],
            'c' => ['label' => 'C', 'sortOrder' => 3, 'extends' => 'b', 'events' => []],
        ]);

        self::assertSame(['view_item_list', 'add_to_cart'], $presets->getPresets()[2]->getEvents());
    }

    /**
     * Presets come by sort order, then by key
     *
     * @return void
     */
    public function testPresetsAreSortedBySortOrderThenKey(): void
    {
        $presets = $this->presets([
            'zeta' => ['label' => 'Zeta', 'sortOrder' => 10, 'events' => ['purchase' => 'purchase']],
            'late' => ['label' => 'Late', 'sortOrder' => 30, 'events' => ['purchase' => 'purchase']],
            'alpha' => ['label' => 'Alpha', 'sortOrder' => 10, 'events' => ['purchase' => 'purchase']],
            'early' => ['label' => 'Early', 'sortOrder' => 1, 'events' => ['purchase' => 'purchase']],
        ]);

        self::assertSame(
            ['early', 'alpha', 'zeta', 'late'],
            array_map(static fn (EventPresetInterface $preset): string => $preset->getKey(), $presets->getPresets())
        );
    }

    /**
     * A preset whose events are all unknown is dropped without a word, also when it extends one that is too
     *
     * @return void
     */
    public function testPresetWithoutKnownEventsIsDropped(): void
    {
        $presets = $this->presets([
            'gone' => ['label' => 'Gone', 'events' => ['made_up' => 'made_up']],
            'gone_too' => ['label' => 'Gone too', 'extends' => 'gone', 'events' => []],
            'kept' => ['label' => 'Kept', 'events' => ['purchase' => 'purchase']],
        ]);

        self::assertSame(
            ['kept'],
            array_map(static fn (EventPresetInterface $preset): string => $preset->getKey(), $presets->getPresets())
        );
    }

    /**
     * A registration that would silently change a selection is refused, naming the preset
     *
     * @dataProvider unusableRegistrations
     * @param array<mixed> $registrations
     * @param string $named The preset key the message names
     * @return void
     */
    #[DataProvider('unusableRegistrations')]
    public function testUnusableRegistrationIsRefused(array $registrations, string $named): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('"%s"', $named));

        $this->presets($registrations);
    }

    /**
     * Registrations the list refuses, with the preset each message names
     *
     * @return array<string, array{array<mixed>, string}>
     */
    public static function unusableRegistrations(): array
    {
        $events = ['purchase' => 'purchase'];

        return [
            'missing label' => [['plain' => ['events' => $events]], 'plain'],
            'blank label' => [['plain' => ['label' => ' ', 'events' => $events]], 'plain'],
            'unknown extends' => [
                ['child' => ['label' => 'Child', 'extends' => 'nobody', 'events' => $events]],
                'child',
            ],
            'extends itself' => [['loop' => ['label' => 'Loop', 'extends' => 'loop', 'events' => $events]], 'loop'],
            'extends cycle' => [
                [
                    'first' => ['label' => 'First', 'extends' => 'second', 'events' => $events],
                    'second' => ['label' => 'Second', 'extends' => 'first', 'events' => $events],
                ],
                'first',
            ],
            'upper-case key' => [['Standard' => ['label' => 'Standard', 'events' => $events]], 'Standard'],
            'key with a dash' => [['shop-plus' => ['label' => 'Shop plus', 'events' => $events]], 'shop-plus'],
            'numeric key' => [[7 => ['label' => 'Seven', 'events' => $events]], '7'],
            'not an array' => [['plain' => 'Plain'], 'plain'],
            'sort order not a number' => [
                ['plain' => ['label' => 'Plain', 'sortOrder' => 'first', 'events' => $events]],
                'plain',
            ],
            'icon not a glyph name' => [
                ['plain' => ['label' => 'Plain', 'icon' => 'Bolt!', 'events' => $events]],
                'plain',
            ],
            'events not a list' => [['plain' => ['label' => 'Plain', 'events' => 'purchase']], 'plain'],
            'event not a name' => [['plain' => ['label' => 'Plain', 'events' => ['purchase' => 3]]], 'plain'],
        ];
    }

    /**
     * A preset is valid by construction: a bad key, an empty label, a bad icon, no events or a bad event name are
     * refused
     *
     * @return void
     */
    public function testPresetIsValidByConstruction(): void
    {
        $preset = new EventPreset('standard', 'Standard', ['view_item', 'view_item', 'purchase'], 'Text', 10, 'bolt');
        self::assertSame(['view_item', 'purchase'], $preset->getEvents());
        self::assertSame('bolt', $preset->getIcon());
        self::assertNull((new EventPreset('minimal', 'Minimal', ['purchase']))->getIcon());

        foreach ([
            static fn (): EventPreset => new EventPreset('Bad', 'Label', ['purchase']),
            static fn (): EventPreset => new EventPreset('good', ' ', ['purchase']),
            static fn (): EventPreset => new EventPreset('good', 'Label', []),
            static fn (): EventPreset => new EventPreset('good', 'Label', ['Not An Event']),
            static fn (): EventPreset => new EventPreset('good', 'Label', ['purchase'], '', 0, 'no glyph'),
        ] as $index => $create) {
            try {
                $create();
                self::fail(sprintf('Unusable preset %d must be refused.', $index));
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * Build the presets over the known events of this test
     *
     * @param array<mixed> $registrations
     * @return EventPresets
     */
    private function presets(array $registrations): EventPresets
    {
        $known = [];
        foreach (self::KNOWN as $eventName) {
            $known[$eventName] = ucfirst(str_replace('_', ' ', $eventName));
        }

        return new EventPresets(
            new KnownEvents(new KnownEventFactoryStub(), $known),
            new EventPresetFactoryStub(),
            $registrations
        );
    }

    /**
     * Describe presets as plain values
     *
     * @param list<EventPresetInterface> $presets
     * @return list<array{string, string, string, int, string|null, list<string>}>
     */
    private function described(array $presets): array
    {
        return array_map(
            static fn (EventPresetInterface $preset): array => [
                $preset->getKey(),
                $preset->getLabel(),
                $preset->getDescription(),
                $preset->getSortOrder(),
                $preset->getIcon(),
                $preset->getEvents(),
            ],
            $presets
        );
    }
}
