<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Model\CartTrackingPolicy;
use Hryvinskyi\DataLayer\Model\PriceVisibility;
use Hryvinskyi\DataLayer\Model\PriceVisibilityOverride;
use Hryvinskyi\DataLayerApi\Api\CartTrackingPolicyInterface;
use Hryvinskyi\DataLayerApi\Api\PriceVisibilityInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\TestCase;

/**
 * Price visibility and cart tracking hold only when every contribution agrees; with none, they always hold
 */
class CompositePoliciesTest extends TestCase
{
    /**
     * With no contribution, every price is visible
     *
     * @return void
     */
    public function testEveryPriceIsVisibleWithoutContributions(): void
    {
        self::assertTrue(
            (new PriceVisibility(new PriceVisibilityOverride()))->isPriceVisible(
                $this->createMock(ProductInterface::class)
            )
        );
    }

    /**
     * A price is visible when every contribution says so, and hidden when one does not
     *
     * @return void
     */
    public function testPriceIsVisibleOnlyWhenEveryContributionAgrees(): void
    {
        $product = $this->createMock(ProductInterface::class);

        self::assertTrue(
            (new PriceVisibility(
                new PriceVisibilityOverride(),
                ['a' => $this->visibility(true), 'b' => $this->visibility(true)]
            ))
                ->isPriceVisible($product)
        );
        self::assertFalse(
            (new PriceVisibility(
                new PriceVisibilityOverride(),
                ['a' => $this->visibility(true), 'b' => $this->visibility(false)]
            ))
                ->isPriceVisible($product)
        );
    }

    /**
     * A forced answer is given for every product without asking a contribution, both ways; once the work is over the
     * contributions decide again
     *
     * @return void
     */
    public function testForcedAnswerShortCircuitsContributions(): void
    {
        $product = $this->createMock(ProductInterface::class);
        $hiding = $this->counting(false);
        $showing = $this->counting(true);
        $override = new PriceVisibilityOverride();
        $hidingComposite = new PriceVisibility($override, ['hide' => $hiding]);
        $showingComposite = new PriceVisibility($override, ['show' => $showing]);

        $forced = [
            $override->withAnswer(true, fn (): bool => $hidingComposite->isPriceVisible($product)),
            $override->withAnswer(false, fn (): bool => $showingComposite->isPriceVisible($product)),
        ];
        $askedWhileForced = $hiding->asked + $showing->asked;
        $afterwards = [$hidingComposite->isPriceVisible($product), $showingComposite->isPriceVisible($product)];

        self::assertSame([true, false], $forced);
        self::assertSame([0, [false, true], 2], [$askedWhileForced, $afterwards, $hiding->asked + $showing->asked]);
    }

    /**
     * A contribution that is not a price visibility rule is refused at construction
     *
     * @return void
     */
    public function testForeignContributionIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PriceVisibility(new PriceVisibilityOverride(), ['wrong' => new \stdClass()]);
    }

    /**
     * With no rule, every cart is tracked
     *
     * @return void
     */
    public function testEveryCartIsTrackedWithoutRules(): void
    {
        self::assertTrue((new CartTrackingPolicy())->isTracked($this->createMock(CartInterface::class)));
    }

    /**
     * A cart is tracked when every rule says so, and not when one does not
     *
     * @return void
     */
    public function testCartIsTrackedOnlyWhenEveryRuleAgrees(): void
    {
        $cart = $this->createMock(CartInterface::class);

        self::assertTrue(
            (new CartTrackingPolicy(['a' => $this->tracking(true), 'b' => $this->tracking(true)]))->isTracked($cart)
        );
        self::assertFalse(
            (new CartTrackingPolicy(['a' => $this->tracking(false), 'b' => $this->tracking(true)]))->isTracked($cart)
        );
    }

    /**
     * A rule that is not a cart tracking rule is refused at construction
     *
     * @return void
     */
    public function testForeignRuleIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CartTrackingPolicy(['wrong' => 'yes']);
    }

    /**
     * A price visibility contribution with a fixed answer
     *
     * @param bool $visible
     * @return PriceVisibilityInterface
     */
    private function visibility(bool $visible): PriceVisibilityInterface
    {
        return new class ($visible) implements PriceVisibilityInterface {
            /**
             * @param bool $visible
             */
            public function __construct(private readonly bool $visible)
            {
            }

            /**
             * @inheritDoc
             */
            public function isPriceVisible(ProductInterface $product): bool
            {
                return $this->visible;
            }
        };
    }

    /**
     * A price visibility contribution with a fixed answer that counts how often it is asked
     *
     * @param bool $visible
     * @return PriceVisibilityInterface&object{asked: int}
     */
    private function counting(bool $visible): PriceVisibilityInterface
    {
        return new class ($visible) implements PriceVisibilityInterface {
            /**
             * @var int How often the contribution was asked
             */
            public int $asked = 0;

            /**
             * @param bool $visible
             */
            public function __construct(private readonly bool $visible)
            {
            }

            /**
             * @inheritDoc
             */
            public function isPriceVisible(ProductInterface $product): bool
            {
                $this->asked++;

                return $this->visible;
            }
        };
    }

    /**
     * A cart tracking rule with a fixed answer
     *
     * @param bool $tracked
     * @return CartTrackingPolicyInterface
     */
    private function tracking(bool $tracked): CartTrackingPolicyInterface
    {
        return new class ($tracked) implements CartTrackingPolicyInterface {
            /**
             * @param bool $tracked
             */
            public function __construct(private readonly bool $tracked)
            {
            }

            /**
             * @inheritDoc
             */
            public function isTracked(CartInterface $cart): bool
            {
                return $this->tracked;
            }
        };
    }
}
