<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayerApi\Api\CartTrackingPolicyInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * A cart's events are reported only when every rule says so
 *
 * Rules are registered through the `rules` argument; with none, every cart is tracked.
 */
class CartTrackingPolicy implements CartTrackingPolicyInterface
{
    /**
     * @var list<CartTrackingPolicyInterface>
     */
    private readonly array $rules;

    /**
     * @param array<mixed> $rules Each a CartTrackingPolicyInterface
     * @throws \InvalidArgumentException When a rule is not a CartTrackingPolicyInterface
     */
    public function __construct(array $rules = [])
    {
        $accepted = [];
        foreach ($rules as $name => $rule) {
            if (!$rule instanceof CartTrackingPolicyInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'The cart tracking rule "%s" must implement %s.',
                    (string) $name,
                    CartTrackingPolicyInterface::class
                ));
            }
            $accepted[] = $rule;
        }
        $this->rules = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function isTracked(CartInterface $cart): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule->isTracked($cart)) {
                return false;
            }
        }

        return true;
    }
}
