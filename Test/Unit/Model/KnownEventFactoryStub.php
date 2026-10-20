<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Hryvinskyi\DataLayer\Api\Data\KnownEventInterface;
use Hryvinskyi\DataLayer\Api\Data\KnownEventInterfaceFactory;
use Hryvinskyi\DataLayer\Model\Data\KnownEvent;

/**
 * Builds known-event descriptions the way the generated factory does, from named arguments, without a container
 */
class KnownEventFactoryStub extends KnownEventInterfaceFactory
{
    /**
     * Build without an object manager
     */
    public function __construct()
    {
    }

    /**
     * Build a description from named arguments
     *
     * @param array<string, mixed> $data
     * @return KnownEventInterface
     */
    public function create(array $data = [])
    {
        $text = static fn (string $key): string => is_string($data[$key] ?? null) ? $data[$key] : '';

        return new KnownEvent(
            $text('name'),
            $text('label'),
            $text('group'),
            $text('trigger'),
            $text('source'),
            $text('hint'),
            ($data['browserOriginated'] ?? false) === true,
            $text('groupCode'),
            $text('sourceCode')
        );
    }
}
