<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\RegistrationInRequestInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Remembers that a shopper registered in the current request
 *
 * The container shares one instance per request, so the mark the sign-up observer sets is the one the sign-in
 * observer reads; it is cleared when an application server starts the next request.
 */
class RegistrationInRequest implements RegistrationInRequestInterface, ResetAfterRequestInterface
{
    /**
     * @var bool
     */
    private bool $marked = false;

    /**
     * @inheritDoc
     */
    public function mark(): void
    {
        $this->marked = true;
    }

    /**
     * @inheritDoc
     */
    public function isMarked(): bool
    {
        return $this->marked;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->marked = false;
    }
}
