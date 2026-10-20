<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\Event\CartValueInterface;
use Hryvinskyi\DataLayer\Model\Config\CheckoutStepValueBasis;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Values a checkout step's cart the way the cart's store view says: by the cart value registered for its checkout step
 * value basis
 *
 * The cart values are a pool keyed by basis code, one per known basis; a pool that misses one fails when it is built,
 * so a store view can never pick a basis nobody values.
 */
class CheckoutStepValue implements CartValueInterface
{
    /**
     * @var array<string, CartValueInterface>
     */
    private readonly array $bases;

    /**
     * @param ConfigInterface $config
     * @param array $bases Keyed by checkout step value basis code
     * @throws \InvalidArgumentException When an entry is not a cart value or a known basis has none
     * @phpstan-param array<string|int, mixed> $bases
     */
    public function __construct(
        private readonly ConfigInterface $config,
        array $bases = []
    ) {
        $accepted = [];
        foreach ($bases as $code => $value) {
            if (!$value instanceof CartValueInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'The checkout step value "%s" must implement %s.',
                    (string) $code,
                    CartValueInterface::class
                ));
            }
            $accepted[(string) $code] = $value;
        }
        foreach (CheckoutStepValueBasis::cases() as $known) {
            if (!isset($accepted[$known->value])) {
                throw new \InvalidArgumentException(
                    sprintf('No checkout step value is registered for "%s".', $known->value)
                );
            }
        }
        $this->bases = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function valueOf(CartInterface $cart, array $items): float
    {
        $basis = $this->config->getCheckoutStepValueBasis((int) $cart->getStoreId());

        return $this->bases[$basis->value]->valueOf($cart, $items);
    }
}
