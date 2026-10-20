<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Session;

use Hryvinskyi\DataLayer\Api\Session\VisitorSessionInterface;
use Magento\Framework\Session\Generic;

/**
 * Reads and writes the data layer's own session namespace
 *
 * Typed on the framework's generic session, the type of the namespace's own session, because only a session object
 * gives access to the namespace's stored data.
 */
class VisitorSession implements VisitorSessionInterface
{
    /**
     * @param Generic $session The data layer's own session namespace
     */
    public function __construct(private readonly Generic $session)
    {
    }

    /**
     * @inheritDoc
     */
    public function get(string $key): mixed
    {
        return $this->session->getData($key);
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, array|string|int|float|bool $value): void
    {
        $this->session->setData($key, $value);
    }

    /**
     * @inheritDoc
     */
    public function remove(string $key): void
    {
        $this->session->unsetData($key);
    }
}
