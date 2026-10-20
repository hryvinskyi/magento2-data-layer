<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Store-view settings read from an array keyed by path, recording the store each read asked for
 */
class FakeScopeConfig implements ScopeConfigInterface
{
    /**
     * @var list<int|string|null>
     */
    public array $askedScopes = [];

    /**
     * @param array<string,mixed> $values
     */
    public function __construct(private readonly array $values = [])
    {
    }

    /**
     * @inheritDoc
     */
    public function getValue($path, $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null)
    {
        $this->askedScopes[] = is_int($scopeCode) || is_string($scopeCode) ? $scopeCode : null;

        return $this->values[$path] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function isSetFlag($path, $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null)
    {
        return (bool) $this->getValue($path, $scopeType, $scopeCode);
    }
}
