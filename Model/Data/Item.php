<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Data;

use Hryvinskyi\DataLayerApi\Api\Data\ItemInterface;

/**
 * One GA4 item, valid by construction
 *
 * An item carries a non-empty `item_id` or `item_name`; every parameter name has the GA4 name shape and every value
 * is a string, an integer or a finite number. Anything else is refused when the item is created.
 */
class Item implements ItemInterface
{
    /**
     * @var array<string, string|int|float>
     */
    private readonly array $parameters;

    /**
     * @param array<mixed> $parameters GA4 item parameters keyed by name
     * @throws \InvalidArgumentException When a name or a value is not acceptable, or the item has neither a non-empty
     *     `item_id` nor a non-empty `item_name`
     */
    public function __construct(array $parameters)
    {
        $accepted = [];
        foreach ($parameters as $name => $value) {
            if (!is_string($name) || preg_match(Ga4Name::PATTERN, $name) !== 1) {
                throw new \InvalidArgumentException(
                    sprintf('"%s" is not a valid GA4 item parameter name.', (string) $name)
                );
            }
            if (!is_string($value) && !is_int($value) && !(is_float($value) && is_finite($value))) {
                throw new \InvalidArgumentException(
                    sprintf('The GA4 item parameter "%s" must be a string, an integer or a finite number.', $name)
                );
            }
            $accepted[$name] = $value;
        }

        if (!$this->isPresent($accepted['item_id'] ?? null) && !$this->isPresent($accepted['item_name'] ?? null)) {
            throw new \InvalidArgumentException('A GA4 item needs a non-empty "item_id" or "item_name".');
        }

        $this->parameters = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Whether an identifying value is present: a number, or a string with more than white space
     *
     * @param string|int|float|null $value
     * @return bool
     */
    private function isPresent(string|int|float|null $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }
}
