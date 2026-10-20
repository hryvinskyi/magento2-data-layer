<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Cart;

use Hryvinskyi\DataLayer\Api\Cart\CartEventNamingInterface;
use Hryvinskyi\DataLayerApi\Api\CartKindInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Names a cart's events by the first registered kind that recognises the cart
 *
 * Kinds are registered through the `kinds` argument and asked in its order (`sortOrder`); with none, or when none
 * recognises the cart, the cart is a shopping cart and every event keeps its name. A registration that is not a cart
 * kind fails when the object is built, so a typo in the wiring cannot let a cart of that kind report a shopping
 * cart's events.
 */
class CartEventNaming implements CartEventNamingInterface
{
    /**
     * @var list<CartKindInterface>
     */
    private readonly array $kinds;

    /**
     * @param array<mixed> $kinds Each a CartKindInterface
     * @throws \InvalidArgumentException When a registration is not a CartKindInterface
     */
    public function __construct(array $kinds = [])
    {
        $accepted = [];
        foreach ($kinds as $name => $kind) {
            if (!$kind instanceof CartKindInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'The cart kind "%s" must implement %s.',
                    (string) $name,
                    CartKindInterface::class
                ));
            }
            $accepted[] = $kind;
        }
        $this->kinds = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function eventFor(string $cartEvent, CartInterface $cart): ?string
    {
        foreach ($this->kinds as $kind) {
            if ($kind->isKindOf($cart)) {
                return $kind->eventFor($cartEvent);
            }
        }

        return $cartEvent;
    }
}
