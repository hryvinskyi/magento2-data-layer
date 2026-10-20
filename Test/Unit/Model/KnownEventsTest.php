<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Api\Data\KnownEventInterface;
use Hryvinskyi\DataLayer\Model\Data\KnownEvent;
use Hryvinskyi\DataLayer\Model\KnownEvents;
use Hryvinskyi\DataLayer\Test\Unit\DictionaryRenderer;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\RendererInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The known events keep each registration's label and description, in registration order; a label alone is a
 * registration too, and a registration the admin could not show is refused when the list is built
 */
class KnownEventsTest extends TestCase
{
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
     * The texts arrive from the wiring as plain source-language text and are translated each time they are read, so
     * the same shared list answers in whichever locale reads it; the codes and the name are never translated, and an
     * empty text stays empty
     *
     * @return void
     */
    public function testTextsAreTranslatedWhenRead(): void
    {
        $known = new KnownEvents(new KnownEventFactoryStub(), [
            'view_item' => ['label' => 'View item', 'group' => 'Browsing', 'groupCode' => 'browsing',
                'trigger' => 'Product page viewed', 'source' => 'Page', 'sourceCode' => 'page',
                'hint' => 'Sent from the page'],
            'login' => 'Login',
        ]);
        [$viewItem, $login] = $known->getEvents();

        Phrase::setRenderer(new DictionaryRenderer([
            'View item' => 'Produkt angesehen',
            'Browsing' => 'Stöbern',
            'Product page viewed' => 'Produktseite aufgerufen',
            'Page' => 'Seite',
            'Sent from the page' => 'Von der Seite gesendet',
            'Login' => 'Anmeldung',
        ]));
        self::assertSame(
            ['view_item', 'Produkt angesehen', 'Stöbern', 'browsing', 'Produktseite aufgerufen', 'Seite', 'page',
                'Von der Seite gesendet'],
            [$viewItem->getName(), $viewItem->getLabel(), $viewItem->getGroup(), $viewItem->getGroupCode(),
                $viewItem->getTrigger(), $viewItem->getSource(), $viewItem->getSourceCode(), $viewItem->getHint()]
        );
        self::assertSame(['Anmeldung', '', '', '', ''], [
            $login->getLabel(),
            $login->getGroup(),
            $login->getTrigger(),
            $login->getSource(),
            $login->getHint(),
        ]);
        self::assertSame(['view_item' => 'Produkt angesehen', 'login' => 'Anmeldung'], $known->getLabels());

        Phrase::setRenderer(new DictionaryRenderer(['View item' => 'Produit consulté']));
        self::assertSame('Produit consulté', $viewItem->getLabel());
        self::assertSame('Browsing', $viewItem->getGroup());
        self::assertSame(['view_item' => 'Produit consulté', 'login' => 'Login'], $known->getLabels());
    }

    /**
     * Described registrations come back as descriptions, trimmed, in the order they were registered
     *
     * @return void
     */
    public function testRegistrationsAreDescribedInOrder(): void
    {
        $known = new KnownEvents(new KnownEventFactoryStub(), [
            'view_item' => ['label' => 'View item', 'group' => 'Browsing', 'trigger' => 'Product page viewed',
                'source' => 'Page'],
            'purchase' => ['label' => 'Purchase', 'group' => 'Checkout', 'trigger' => ' Order placed ',
                'source' => 'Order', 'hint' => 'Server = sent when the order is placed or paid'],
            'sign_up' => 'Sign up',
        ]);

        self::assertSame(
            [
                ['view_item', 'View item', 'Browsing', 'Product page viewed', 'Page', ''],
                ['purchase', 'Purchase', 'Checkout', 'Order placed', 'Order',
                    'Server = sent when the order is placed or paid'],
                ['sign_up', 'Sign up', '', '', '', ''],
            ],
            array_map(
                static fn (KnownEventInterface $event): array => [
                    $event->getName(),
                    $event->getLabel(),
                    $event->getGroup(),
                    $event->getTrigger(),
                    $event->getSource(),
                    $event->getHint(),
                ],
                $known->getEvents()
            )
        );
        self::assertSame(
            ['view_item' => 'View item', 'purchase' => 'Purchase', 'sign_up' => 'Sign up'],
            $known->getLabels()
        );
        self::assertTrue($known->isKnown('sign_up'));
        self::assertFalse($known->isKnown('login'));
    }

    /**
     * Only a registration that says so is detected by the browser; a label alone, an explicit false and an unknown
     * event are not, and the description carries the same answer
     *
     * @return void
     */
    public function testBrowserOriginatedOnlyWhenRegisteredSo(): void
    {
        $known = new KnownEvents(new KnownEventFactoryStub(), [
            'view_item' => ['label' => 'View item', 'source' => 'Page', 'browserOriginated' => true],
            'add_to_cart' => ['label' => 'Add to cart', 'source' => 'Server', 'browserOriginated' => false],
            'purchase' => ['label' => 'Purchase', 'source' => 'Order'],
            'sign_up' => 'Sign up',
        ]);

        self::assertTrue($known->isBrowserOriginated('view_item'));
        self::assertFalse($known->isBrowserOriginated('add_to_cart'));
        self::assertFalse($known->isBrowserOriginated('purchase'));
        self::assertFalse($known->isBrowserOriginated('sign_up'));
        self::assertFalse($known->isBrowserOriginated('select_item'));
        self::assertSame(
            ['view_item' => true, 'add_to_cart' => false, 'purchase' => false, 'sign_up' => false],
            array_combine(
                array_map(static fn (KnownEventInterface $event): string => $event->getName(), $known->getEvents()),
                array_map(
                    static fn (KnownEventInterface $event): bool => $event->isBrowserOriginated(),
                    $known->getEvents()
                )
            )
        );
    }

    /**
     * A registration without a label, with a description that is not text or a browser flag that is not a boolean,
     * is refused
     *
     * @dataProvider unusableRegistrations
     * @param array<mixed> $events
     * @return void
     */
    #[DataProvider('unusableRegistrations')]
    public function testUnusableRegistrationIsRefused(array $events): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new KnownEvents(new KnownEventFactoryStub(), $events);
    }

    /**
     * Registrations the admin could not show
     *
     * @return array<string, array{array<mixed>}>
     */
    public static function unusableRegistrations(): array
    {
        return [
            'no label' => [['purchase' => ['group' => 'Checkout']]],
            'blank label' => [['purchase' => '  ']],
            'label that is not text' => [['purchase' => ['label' => ['Purchase']]]],
            'trigger that is not text' => [['purchase' => ['label' => 'Purchase', 'trigger' => 3]]],
            'name that is not an event name' => [['Purchase Now' => 'Purchase']],
            'browser flag that is not a boolean' => [['view_item' => ['label' => 'View item',
                'browserOriginated' => 'true']]],
        ];
    }

    /**
     * Group and source codes come back as registered, untranslated; left out, they are empty
     *
     * @return void
     */
    public function testGroupAndSourceCodesComeBackAsRegistered(): void
    {
        $known = new KnownEvents(new KnownEventFactoryStub(), [
            'view_item' => ['label' => 'View item', 'group' => 'Browsing', 'groupCode' => 'browsing',
                'source' => 'Page', 'sourceCode' => 'page'],
            'sign_up' => ['label' => 'Sign up', 'groupCode' => 'account', 'sourceCode' => 'server'],
            'login' => 'Login',
        ]);

        self::assertSame(
            ['view_item' => ['browsing', 'page'], 'sign_up' => ['account', 'server'], 'login' => ['', '']],
            array_combine(
                array_map(static fn (KnownEventInterface $event): string => $event->getName(), $known->getEvents()),
                array_map(
                    static fn (KnownEventInterface $event): array => [$event->getGroupCode(), $event->getSourceCode()],
                    $known->getEvents()
                )
            )
        );
    }

    /**
     * A group or source code that is not lower-case letters and underscores is refused, in the list and in a
     * description
     *
     * @dataProvider unusableCodes
     * @param mixed $code
     * @return void
     */
    #[DataProvider('unusableCodes')]
    public function testUnusableCodeIsRefused(mixed $code): void
    {
        foreach (['groupCode', 'sourceCode'] as $key) {
            try {
                new KnownEvents(new KnownEventFactoryStub(), ['purchase' => ['label' => 'Purchase', $key => $code]]);
                self::fail(sprintf('The %s %s must be refused.', $key, var_export($code, true)));
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
        if (!is_string($code)) {
            return;
        }
        foreach ([[$code, ''], ['', $code]] as [$groupCode, $sourceCode]) {
            try {
                new KnownEvent('purchase', 'Purchase', '', '', '', '', false, $groupCode, $sourceCode);
                self::fail(sprintf('The code "%s" must be refused by a description.', $code));
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * Codes the list refuses
     *
     * @return array<string, array{mixed}>
     */
    public static function unusableCodes(): array
    {
        return [
            'upper case' => ['Checkout'],
            'digit' => ['step2'],
            'dash' => ['add-to-cart'],
            'space' => ['check out'],
            'translated text' => [new Phrase('checkout')],
            'number' => [3],
        ];
    }

    /**
     * A description refuses a name that is not an event name and an empty label
     *
     * @return void
     */
    public function testDescriptionIsValidByConstruction(): void
    {
        $event = new KnownEvent('sign_up', 'Sign up', 'Account');
        self::assertSame('Account', $event->getGroup());
        self::assertFalse($event->isBrowserOriginated());
        self::assertTrue((new KnownEvent('view_item', 'View item', '', '', 'Page', '', true))->isBrowserOriginated());

        $this->expectException(\InvalidArgumentException::class);
        new KnownEvent('sign_up', ' ');
    }
}
