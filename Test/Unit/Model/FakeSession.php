<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model;

use Magento\Framework\Session\Generic;
use Magento\Framework\Session\Storage;

/**
 * A session namespace kept in memory, without starting a PHP session
 */
class FakeSession extends Generic
{
    /**
     * Keep the namespace's data in an in-memory storage
     */
    public function __construct()
    {
        $this->storage = new Storage('test');
    }
}
