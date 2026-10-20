<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Observer;

use Hryvinskyi\DataLayer\Api\Event\AccountEventBuilderInterface;
use Hryvinskyi\DataLayer\Api\RegistrationInRequestInterface;
use Hryvinskyi\DataLayerApi\Api\EventDispatcherInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Reports a shopper registering on the storefront
 *
 * The request is marked first, so the sign-in the registration performs next in the same request is not reported as
 * well. A failure is logged and never reaches the registration.
 */
class ReportSignUp implements ObserverInterface
{
    /**
     * @param AccountEventBuilderInterface $builder
     * @param RegistrationInRequestInterface $registration
     * @param EventDispatcherInterface $dispatcher
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly AccountEventBuilderInterface $builder,
        private readonly RegistrationInRequestInterface $registration,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $this->registration->mark();

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $event = $this->builder->build($storeId);
            if ($event !== null) {
                $this->dispatcher->dispatch($event, $storeId);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('A GA4 sign-up could not be reported.', ['exception' => $exception]);
        }
    }
}
