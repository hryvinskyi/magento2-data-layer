<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\PriceVisibilityOverrideInterface;
use Hryvinskyi\DataLayer\Api\PriceVisibilityOverrideReaderInterface;

/**
 * The request's forced price-visibility answer: set around one piece of work, read by price visibility, cleared
 * afterwards
 *
 * One shared instance serves both roles, so price visibility reads the answer the work was given. The answer is
 * always cleared, whether the work returns or throws, so a forced answer never reaches the rest of the request; an
 * override is never opened inside another, because the inner answer would silently replace the outer one.
 */
class PriceVisibilityOverride implements PriceVisibilityOverrideInterface, PriceVisibilityOverrideReaderInterface
{
    /**
     * @var bool|null Null while no answer is forced
     */
    private ?bool $forced = null;

    /**
     * @inheritDoc
     */
    public function withAnswer(bool $visible, callable $work): mixed
    {
        if ($this->forced !== null) {
            throw new \LogicException('A price-visibility answer is already forced; overrides cannot be nested.');
        }

        $this->forced = $visible;
        try {
            return $work();
        } finally {
            $this->forced = null;
        }
    }

    /**
     * @inheritDoc
     */
    public function forced(): ?bool
    {
        return $this->forced;
    }
}
