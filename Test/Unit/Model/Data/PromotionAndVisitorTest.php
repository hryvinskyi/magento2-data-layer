<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Data;

use Hryvinskyi\DataLayer\Model\Data\Promotion;
use Hryvinskyi\DataLayer\Model\Data\VisitorContext;
use PHPUnit\Framework\TestCase;

/**
 * A promotion is identified by an id or a name and reports only its non-empty parameters; a visitor's click ids are
 * strings keyed by name
 */
class PromotionAndVisitorTest extends TestCase
{
    /**
     * Every non-empty value becomes a GA4 promotion parameter; empty ones are left out
     *
     * @return void
     */
    public function testPromotionReportsOnlyNonEmptyParameters(): void
    {
        $promotion = new Promotion('banner_5', 'Summer sale', '', 'hero', '', '/sale');

        self::assertSame(
            ['promotion_id' => 'banner_5', 'promotion_name' => 'Summer sale', 'creative_slot' => 'hero'],
            $promotion->toParameters()
        );
        self::assertSame('/sale', $promotion->getTargetUrl());
        self::assertSame('', $promotion->getCreativeName());
        self::assertSame('', $promotion->getLocationId());
    }

    /**
     * A promotion with every parameter set reports all five
     *
     * @return void
     */
    public function testPromotionWithEveryParameter(): void
    {
        $promotion = new Promotion('p', 'n', 'c', 's', 'l');

        self::assertSame(
            [
                'promotion_id' => 'p',
                'promotion_name' => 'n',
                'creative_name' => 'c',
                'creative_slot' => 's',
                'location_id' => 'l',
            ],
            $promotion->toParameters()
        );
        self::assertSame('p', $promotion->getPromotionId());
        self::assertSame('n', $promotion->getPromotionName());
        self::assertSame('c', $promotion->getCreativeName());
        self::assertSame('s', $promotion->getCreativeSlot());
        self::assertSame('l', $promotion->getLocationId());
    }

    /**
     * A promotion named only is accepted; one with neither id nor name is refused
     *
     * @return void
     */
    public function testPromotionNeedsAnIdOrAName(): void
    {
        self::assertSame(['promotion_name' => 'Sale'], (new Promotion('', 'Sale'))->toParameters());

        $this->expectException(\InvalidArgumentException::class);
        new Promotion(' ', '', 'creative');
    }

    /**
     * A visitor gives back what it was built from
     *
     * @return void
     */
    public function testVisitorGivesBackWhatItWasBuiltFrom(): void
    {
        $at = new \DateTimeImmutable('2026-10-15 10:00:00');
        $visitor = new VisitorContext($at, 'c1', 's1', 'u1', ['gclid' => 'g'], 'UA', 'https://a/', 'https://b/');

        self::assertSame($at, $visitor->getOccurredAt());
        self::assertSame('c1', $visitor->getClientId());
        self::assertSame('s1', $visitor->getSessionId());
        self::assertSame('u1', $visitor->getUserId());
        self::assertSame(['gclid' => 'g'], $visitor->getClickIds());
        self::assertSame('UA', $visitor->getUserAgent());
        self::assertSame('https://a/', $visitor->getPageLocation());
        self::assertSame('https://b/', $visitor->getPageReferrer());
    }

    /**
     * A click id that is not a string keyed by name is refused
     *
     * @return void
     */
    public function testVisitorRefusesClickIdsThatAreNotStrings(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new VisitorContext(new \DateTimeImmutable(), null, null, null, ['gclid' => 5]);
    }
}
