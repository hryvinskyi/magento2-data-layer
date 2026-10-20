<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Event;

use Hryvinskyi\DataLayer\Api\Event\AccountEventBuilderInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;

/**
 * Builds `sign_up` for a shopper who registered on the storefront
 *
 * Not an ecommerce event: its one parameter, `method`, is `website` (the store's own account, as opposed to a
 * third-party sign-in).
 */
class SignUpEventBuilder implements AccountEventBuilderInterface
{
    public const EVENT_NAME = 'sign_up';

    private const METHOD = 'website';

    /**
     * @param EventAvailabilityInterface $availability
     * @param EventInterfaceFactory $eventFactory
     */
    public function __construct(
        private readonly EventAvailabilityInterface $availability,
        private readonly EventInterfaceFactory $eventFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function build(int $storeId): ?EventInterface
    {
        if (!$this->availability->isEnabled(self::EVENT_NAME, $storeId)) {
            return null;
        }

        return $this->eventFactory->create([
            'name' => self::EVENT_NAME,
            'ecommerce' => false,
            'parameters' => ['method' => self::METHOD],
        ]);
    }
}
