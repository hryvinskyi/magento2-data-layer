<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration;

use Magento\TestFramework\Helper\Bootstrap;

/**
 * Gives an integration test typed access to the objects of the real container
 */
trait ResolvesServices
{
    /**
     * Get a shared object from the real container, checked to be of the requested type
     *
     * @template T of object
     * @param class-string<T> $type The type the object must have
     * @param string|null $name The name to resolve, when it differs from the type (a virtual type)
     * @return T
     */
    private function service(string $type, ?string $name = null): object
    {
        return $this->checked(Bootstrap::getObjectManager()->get($name ?? $type), $type);
    }

    /**
     * Create a new object through the real container, checked to be of the requested type
     *
     * @template T of object
     * @param class-string<T> $type The type the object must have
     * @param string|null $name The name to resolve, when it differs from the type (a virtual type)
     * @return T
     */
    private function fresh(string $type, ?string $name = null): object
    {
        return $this->checked(Bootstrap::getObjectManager()->create($name ?? $type), $type);
    }

    /**
     * Check an object's type
     *
     * @template T of object
     * @param mixed $object
     * @param class-string<T> $type
     * @return T
     */
    private function checked(mixed $object, string $type): object
    {
        if (!$object instanceof $type) {
            throw new \LogicException(sprintf('The container returned an object that is not a %s.', $type));
        }

        return $object;
    }
}
