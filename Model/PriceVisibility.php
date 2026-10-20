<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\PriceVisibilityOverrideReaderInterface;
use Hryvinskyi\DataLayerApi\Api\PriceVisibilityInterface;
use Magento\Catalog\Api\Data\ProductInterface;

/**
 * A price is visible only when every contribution says so
 *
 * Contributions are registered through the `contributions` argument; with none, every price is visible. An answer
 * forced for the running request (a preview as a visitor who sees prices, or as one who may not) is given for every
 * product before any contribution is asked.
 */
class PriceVisibility implements PriceVisibilityInterface
{
    /**
     * @var list<PriceVisibilityInterface>
     */
    private readonly array $contributions;

    /**
     * @param PriceVisibilityOverrideReaderInterface $override The answer forced for the running request, if any
     * @param array<mixed> $contributions Each a PriceVisibilityInterface
     * @throws \InvalidArgumentException When a contribution is not a PriceVisibilityInterface
     */
    public function __construct(
        private readonly PriceVisibilityOverrideReaderInterface $override,
        array $contributions = []
    ) {
        $accepted = [];
        foreach ($contributions as $name => $contribution) {
            if (!$contribution instanceof PriceVisibilityInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'The price visibility contribution "%s" must implement %s.',
                    (string) $name,
                    PriceVisibilityInterface::class
                ));
            }
            $accepted[] = $contribution;
        }
        $this->contributions = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function isPriceVisible(ProductInterface $product): bool
    {
        $forced = $this->override->forced();
        if ($forced !== null) {
            return $forced;
        }
        foreach ($this->contributions as $contribution) {
            if (!$contribution->isPriceVisible($product)) {
                return false;
            }
        }

        return true;
    }
}
