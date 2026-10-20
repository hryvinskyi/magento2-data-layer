<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model;

use Hryvinskyi\DataLayer\Api\HiddenPricePolicyInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Hryvinskyi\DataLayerApi\Api\ItemBuilderInterface;
use Hryvinskyi\DataLayerApi\Api\ItemFieldProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds an event's items by asking every item field provider once for all of the event's subjects
 *
 * Providers run in the order they are registered (the `sortOrder` of the `providers` items); a later provider's
 * parameter replaces an earlier one's of the same name. A subject whose price the viewer may not see loses its money
 * parameters (`price`, `discount`). A provider that fails is logged and contributes nothing, and a subject that
 * cannot make a valid item (no `item_id` and no `item_name`, or an unacceptable parameter) is logged and left out:
 * the event is still built from everything else.
 */
class ItemBuilder implements ItemBuilderInterface
{
    private const MONEY_PARAMETERS = ['price', 'discount'];

    /**
     * @var list<ItemFieldProviderInterface>
     */
    private readonly array $providers;

    /**
     * @param ItemInterfaceFactory $itemFactory
     * @param HiddenPricePolicyInterface $hiddenPricePolicy
     * @param LoggerInterface $logger
     * @param array<mixed> $providers Each an ItemFieldProviderInterface, in the order they run
     * @throws \InvalidArgumentException When a provider is not an ItemFieldProviderInterface
     */
    public function __construct(
        private readonly ItemInterfaceFactory $itemFactory,
        private readonly HiddenPricePolicyInterface $hiddenPricePolicy,
        private readonly LoggerInterface $logger,
        array $providers = []
    ) {
        $accepted = [];
        foreach ($providers as $name => $provider) {
            if (!$provider instanceof ItemFieldProviderInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'The item field provider "%s" must implement %s.',
                    (string) $name,
                    ItemFieldProviderInterface::class
                ));
            }
            $accepted[] = $provider;
        }
        $this->providers = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function build(array $subjects, string $eventName): array
    {
        $parameters = array_fill_keys(array_keys($subjects), []);
        foreach ($this->providers as $provider) {
            foreach ($this->contribution($provider, $subjects, $eventName) as $key => $fields) {
                if (isset($parameters[$key])) {
                    $parameters[$key] = array_replace($parameters[$key], $fields);
                }
            }
        }

        $items = [];
        foreach ($subjects as $key => $subject) {
            $itemParameters = $parameters[$key];
            if (!$this->hiddenPricePolicy->isPriceVisible($subject)) {
                $itemParameters = array_diff_key($itemParameters, array_flip(self::MONEY_PARAMETERS));
            }
            try {
                $items[] = $this->itemFactory->create(['parameters' => $itemParameters]);
            } catch (\InvalidArgumentException $exception) {
                $this->logger->warning('A GA4 item was left out of an event: it is not a valid item.', [
                    'event' => $eventName,
                    'product_id' => $subject->getProduct()->getId(),
                    'reason' => $exception->getMessage(),
                ]);
            }
        }

        return $items;
    }

    /**
     * Ask one provider for its parameters; a provider that fails contributes nothing
     *
     * @param ItemFieldProviderInterface $provider
     * @param list<ItemSubjectInterface> $subjects
     * @param string $eventName
     * @return array<int, array<string, string|int|float>>
     */
    private function contribution(ItemFieldProviderInterface $provider, array $subjects, string $eventName): array
    {
        try {
            return $provider->provide($subjects, $eventName);
        } catch (\Throwable $exception) {
            $this->logger->error('A GA4 item field provider failed; its parameters were left out.', [
                'provider' => $provider::class,
                'event' => $eventName,
                'exception' => $exception,
            ]);

            return [];
        }
    }
}
