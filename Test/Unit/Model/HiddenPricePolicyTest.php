<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Api\HiddenPriceDecision;
use Hryvinskyi\DataLayer\Model\HiddenPricePolicy;
use Hryvinskyi\DataLayer\Test\Unit\Model\ItemField\SubjectDoubles;
use Hryvinskyi\DataLayerApi\Api\PriceVisibilityInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use PHPUnit\Framework\TestCase;

/**
 * All visible when every price is; otherwise the store's setting; order lines always count as visible
 */
class HiddenPricePolicyTest extends TestCase
{
    use SubjectDoubles;

    /**
     * The decision for each combination of visibility and setting
     *
     * @dataProvider decisionTable
     * @param bool $hidden Whether one catalogue product's price is hidden
     * @param string|null $setting The stored hidden-price setting
     * @param HiddenPriceDecision $expected
     * @return void
     */
    public function testDecisionTable(bool $hidden, ?string $setting, HiddenPriceDecision $expected): void
    {
        $policy = new HiddenPricePolicy(
            $this->visibility($hidden ? [2] : []),
            $this->settings(['hryvinskyi_datalayer/item/hidden_price_handling' => $setting])
        );

        self::assertSame(
            $expected,
            $policy->decide([$this->subject($this->product(1)), $this->subject($this->product(2))], 1)
        );
    }

    /**
     * Visibility × setting → decision
     *
     * @return array<string, array{bool, string|null, HiddenPriceDecision}>
     */
    public static function decisionTable(): array
    {
        return [
            'all visible, omit' => [false, 'omit_price', HiddenPriceDecision::AllVisible],
            'all visible, skip' => [false, 'skip_event', HiddenPriceDecision::AllVisible],
            'hidden, omit' => [true, 'omit_price', HiddenPriceDecision::OmitMoney],
            'hidden, skip' => [true, 'skip_event', HiddenPriceDecision::SkipEvent],
            'hidden, default' => [true, null, HiddenPriceDecision::OmitMoney],
            'hidden, unknown value' => [true, 'whatever', HiddenPriceDecision::OmitMoney],
        ];
    }

    /**
     * An order line's price is visible without asking price visibility
     *
     * @return void
     */
    public function testOrderLinesAreAlwaysVisible(): void
    {
        $visibility = $this->createMock(PriceVisibilityInterface::class);
        $visibility->expects(self::never())->method('isPriceVisible');
        $policy = new HiddenPricePolicy(
            $visibility,
            $this->settings(['hryvinskyi_datalayer/item/hidden_price_handling' => 'skip_event'])
        );
        $subject = $this->subject($this->product(1), ['order' => $this->orderLine()]);

        self::assertSame(HiddenPriceDecision::AllVisible, $policy->decide([$subject]));
        self::assertTrue($policy->isPriceVisible($subject));
    }

    /**
     * A cart line's price follows price visibility
     *
     * @return void
     */
    public function testCartLinesFollowVisibility(): void
    {
        $policy = new HiddenPricePolicy($this->visibility([1]), $this->settings());

        self::assertFalse($policy->isPriceVisible($this->subject($this->product(1), ['cart' => $this->cartLine()])));
        self::assertTrue($policy->isPriceVisible($this->subject($this->product(2), ['cart' => $this->cartLine()])));
    }

    /**
     * Price visibility hiding the given products
     *
     * @param list<int> $hiddenProductIds
     * @return PriceVisibilityInterface
     */
    private function visibility(array $hiddenProductIds): PriceVisibilityInterface
    {
        $visibility = $this->createMock(PriceVisibilityInterface::class);
        $visibility->method('isPriceVisible')->willReturnCallback(
            static fn (ProductInterface $product): bool => !in_array((int) $product->getId(), $hiddenProductIds, true)
        );

        return $visibility;
    }
}
