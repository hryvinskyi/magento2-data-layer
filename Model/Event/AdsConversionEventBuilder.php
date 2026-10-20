<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\Event\ConversionValueSourceInterface;
use Hryvinskyi\DataLayer\Api\Event\OrderEventBuilderInterface;
use Hryvinskyi\DataLayer\Model\Config\AdsValueSource;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Builds `ads_conversion` for an order: the Google Ads conversion that follows its `purchase`
 *
 * A non-ecommerce event with `send_to` (`<conversion id>/<conversion label>`), `transaction_id` (the order's increment
 * id, which Google Ads uses to count an order once) and, when the store view's value source has one, `value` and
 * `currency`. It is built only when the order's store view reports conversions (switched on, with a conversion id and
 * label), does not disable the event, and reports `purchase`: a conversion never counts an order the store does not
 * report as a purchase.
 *
 * The value sources are a pool keyed by value source code, one per known source; a pool that misses one fails when it
 * is built, so a store view can never pick a source nobody values.
 */
class AdsConversionEventBuilder implements OrderEventBuilderInterface
{
    public const EVENT_NAME = 'ads_conversion';

    private const PURCHASE_EVENT = 'purchase';

    /**
     * @var array<string, ConversionValueSourceInterface>
     */
    private readonly array $valueSources;

    /**
     * @param ConfigInterface $config
     * @param EventAvailabilityInterface $availability
     * @param EventInterfaceFactory $eventFactory
     * @param array $valueSources Keyed by value source code
     * @throws \InvalidArgumentException When an entry is not a value source or a known source has none
     * @phpstan-param array<string|int, mixed> $valueSources
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly EventAvailabilityInterface $availability,
        private readonly EventInterfaceFactory $eventFactory,
        array $valueSources = []
    ) {
        $sources = [];
        foreach ($valueSources as $code => $source) {
            if (!$source instanceof ConversionValueSourceInterface) {
                throw new \InvalidArgumentException(sprintf(
                    'The conversion value source "%s" must implement %s.',
                    (string) $code,
                    ConversionValueSourceInterface::class
                ));
            }
            $sources[(string) $code] = $source;
        }
        foreach (AdsValueSource::cases() as $known) {
            if (!isset($sources[$known->value])) {
                throw new \InvalidArgumentException(
                    sprintf('No conversion value source is registered for "%s".', $known->value)
                );
            }
        }
        $this->valueSources = $sources;
    }

    /**
     * @inheritDoc
     */
    public function build(OrderInterface $order): ?EventInterface
    {
        $storeId = (int) $order->getStoreId();
        $transactionId = trim((string) $order->getIncrementId());
        $conversionId = $this->config->getAdsConversionId($storeId);
        $label = $this->config->getAdsConversionLabel($storeId);
        if ($transactionId === '' || $conversionId === null || $label === null
            || !$this->config->isAdsConversionEnabled($storeId)
            || !$this->availability->isEnabled(self::EVENT_NAME, $storeId)
            || !$this->availability->isEnabled(self::PURCHASE_EVENT, $storeId)
        ) {
            return null;
        }

        $parameters = ['send_to' => $conversionId . '/' . $label, 'transaction_id' => $transactionId];
        $value = $this->valueSources[$this->config->getAdsValueSource($storeId)->value]->valueOf($order);
        if ($value !== null) {
            $parameters['value'] = $value->getAmount();
            $parameters['currency'] = $value->getCurrency();
        }

        return $this->eventFactory->create([
            'name' => self::EVENT_NAME,
            'ecommerce' => false,
            'parameters' => $parameters,
        ]);
    }
}
