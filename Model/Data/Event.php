<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Data;

use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemInterface;

/**
 * One GA4 event, valid by construction
 *
 * The event name and every parameter name have the GA4 name shape; every parameter value is a string, an integer, a
 * finite number or a boolean; every item is an item. The names `event`, `ecommerce` and `items` are the keys of the
 * pushed object itself, so no parameter may take them. Anything else is refused when the event is created.
 */
class Event implements EventInterface
{
    private const RESERVED_PARAMETER_NAMES = ['event', 'ecommerce', 'items'];

    /**
     * @var array<string, string|int|float|bool>
     */
    private readonly array $parameters;

    /**
     * @var list<ItemInterface>
     */
    private readonly array $items;

    /**
     * @param string $name The GA4 event name
     * @param bool $ecommerce Whether the parameters and items go under `ecommerce` in the data layer
     * @param array<mixed> $parameters Event-level parameters keyed by GA4 name
     * @param array<mixed> $items The event's items, in the order they are reported
     * @throws \InvalidArgumentException When the name, a parameter or an item is not acceptable
     */
    public function __construct(
        private readonly string $name,
        private readonly bool $ecommerce,
        array $parameters = [],
        array $items = []
    ) {
        if (preg_match(Ga4Name::PATTERN, $name) !== 1) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid GA4 event name.', $name));
        }

        $this->parameters = $this->acceptParameters($parameters);
        $this->items = $this->acceptItems($items);
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function isEcommerce(): bool
    {
        return $this->ecommerce;
    }

    /**
     * @inheritDoc
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @inheritDoc
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @inheritDoc
     *
     * @return array<string, scalar|array<string, scalar|list<array<string, scalar>>>>
     */
    public function toDataLayer(): array
    {
        if (!$this->ecommerce) {
            return ['event' => $this->name] + $this->parameters;
        }

        $ecommerce = $this->parameters;
        if ($this->items !== []) {
            $ecommerce['items'] = array_map(
                static fn (ItemInterface $item): array => $item->getParameters(),
                $this->items
            );
        }

        return ['event' => $this->name, 'ecommerce' => $ecommerce];
    }

    /**
     * Check every parameter's name and value
     *
     * @param array<mixed> $parameters
     * @return array<string, string|int|float|bool>
     * @throws \InvalidArgumentException
     */
    private function acceptParameters(array $parameters): array
    {
        $accepted = [];
        foreach ($parameters as $name => $value) {
            if (!is_string($name) || preg_match(Ga4Name::PATTERN, $name) !== 1) {
                throw new \InvalidArgumentException(
                    sprintf('"%s" is not a valid GA4 event parameter name.', (string) $name)
                );
            }
            if (in_array($name, self::RESERVED_PARAMETER_NAMES, true)) {
                throw new \InvalidArgumentException(
                    sprintf('"%s" names a key of the data layer object and cannot be an event parameter.', $name)
                );
            }
            if (!is_string($value) && !is_int($value) && !is_bool($value)
                && !(is_float($value) && is_finite($value))
            ) {
                throw new \InvalidArgumentException(sprintf(
                    'The GA4 event parameter "%s" must be a string, an integer, a finite number or a boolean.',
                    $name
                ));
            }
            $accepted[$name] = $value;
        }

        return $accepted;
    }

    /**
     * Check that every item is an item, keeping their order
     *
     * @param array<mixed> $items
     * @return list<ItemInterface>
     * @throws \InvalidArgumentException
     */
    private function acceptItems(array $items): array
    {
        $accepted = [];
        foreach ($items as $item) {
            if (!$item instanceof ItemInterface) {
                throw new \InvalidArgumentException(
                    sprintf('Every item of a GA4 event must implement %s.', ItemInterface::class)
                );
            }
            $accepted[] = $item;
        }

        return $accepted;
    }
}
