<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Observer;

use Hryvinskyi\DataLayer\Api\Event\AccountEventBuilderInterface;
use Hryvinskyi\DataLayer\Api\Event\ProductQuantityEventBuilderInterface;
use Hryvinskyi\DataLayer\Model\Data\Event as DataLayerEvent;
use Hryvinskyi\DataLayer\Model\RegistrationInRequest;
use Hryvinskyi\DataLayer\Observer\ReportLogin;
use Hryvinskyi\DataLayer\Observer\ReportSignUp;
use Hryvinskyi\DataLayer\Observer\ReportWishlistAddition;
use Hryvinskyi\DataLayer\Test\Unit\Model\RecordingLogger;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\EventDispatcherInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Wishlist\Model\Item as WishlistItem;
use PHPUnit\Framework\TestCase;

/**
 * A registration is reported as a sign-up only, a sign-in on its own as a login; a wish list addition with the wished
 * quantity; each through the dispatcher for the current store view, and a failure never escapes
 */
class AccountAndWishlistObserversTest extends TestCase
{
    /**
     * @var list<array{string, int}>
     */
    private array $dispatched = [];

    /**
     * @var RecordingLogger
     */
    private RecordingLogger $logger;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    /**
     * A registration followed by its own sign-in in the same request reports a sign-up and no login
     *
     * @return void
     */
    public function testRegistrationReportsSignUpOnly(): void
    {
        $registration = new RegistrationInRequest();
        $signUp = new ReportSignUp(
            $this->builder('sign_up'),
            $registration,
            $this->dispatcher(),
            $this->stores(),
            $this->logger
        );
        $login = $this->login($registration);

        $signUp->execute(new Observer(['event' => new Event([])]));
        $login->execute(new Observer(['event' => new Event([])]));

        self::assertSame([['sign_up', 5]], $this->dispatched);
    }

    /**
     * A sign-in on its own reports a login; a new request forgets the registration
     *
     * @return void
     */
    public function testSignInReportsLogin(): void
    {
        $registration = new RegistrationInRequest();
        $registration->mark();
        $registration->_resetState();
        $login = $this->login($registration);

        $login->execute(new Observer(['event' => new Event([])]));

        self::assertSame([['login', 5]], $this->dispatched);
    }

    /**
     * A failing builder is logged and the sign-in goes on
     *
     * @return void
     */
    public function testFailureIsLogged(): void
    {
        $failing = $this->createMock(AccountEventBuilderInterface::class);
        $failing->method('build')->willThrowException(new \RuntimeException('broken'));

        (new ReportLogin($failing, new RegistrationInRequest(), $this->dispatcher(), $this->stores(), $this->logger))
            ->execute(new Observer(['event' => new Event([])]));

        self::assertSame([], $this->dispatched);
        self::assertCount(1, $this->logger->records);
    }

    /**
     * A wish list addition reports the wished quantity, or one when the wish list item does not say
     *
     * @return void
     */
    public function testWishlistAddition(): void
    {
        $asked = [];
        $builder = $this->createMock(ProductQuantityEventBuilderInterface::class);
        $builder->method('build')->willReturnCallback(
            static function (mixed $product, float $quantity, int $storeId) use (&$asked): DataLayerEvent {
                $asked[] = [$quantity, $storeId];

                return new DataLayerEvent('add_to_wishlist', true);
            }
        );
        $observer = new ReportWishlistAddition($builder, $this->dispatcher(), $this->stores(), $this->logger);
        $item = $this->createMock(WishlistItem::class);
        $item->method('getData')->willReturnCallback(
            static fn (string $key = ''): mixed => $key === 'qty' ? '3.0000' : null
        );
        $product = $this->createMock(Product::class);

        $observer->execute(new Observer(['event' => new Event(['product' => $product, 'item' => $item])]));
        $observer->execute(new Observer(['event' => new Event(['product' => $product, 'item' => 'not an item'])]));
        $observer->execute(new Observer(['event' => new Event(['item' => $item])]));

        self::assertSame([[3.0, 5], [1.0, 5]], $asked);
        self::assertSame([['add_to_wishlist', 5], ['add_to_wishlist', 5]], $this->dispatched);
    }

    /**
     * The sign-in observer with a builder that always builds its event
     *
     * @param RegistrationInRequest $registration
     * @return ReportLogin
     */
    private function login(RegistrationInRequest $registration): ReportLogin
    {
        return new ReportLogin(
            $this->builder('login'),
            $registration,
            $this->dispatcher(),
            $this->stores(),
            $this->logger
        );
    }

    /**
     * An account event builder that always builds its event
     *
     * @param string $eventName
     * @return AccountEventBuilderInterface
     */
    private function builder(string $eventName): AccountEventBuilderInterface
    {
        $builder = $this->createMock(AccountEventBuilderInterface::class);
        $builder->method('build')->willReturnCallback(
            static fn (): DataLayerEvent => new DataLayerEvent($eventName, false)
        );

        return $builder;
    }

    /**
     * A dispatcher that records what it is given
     *
     * @return EventDispatcherInterface
     */
    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(function (EventInterface $event, int $storeId): void {
            $this->dispatched[] = [$event->getName(), $storeId];
        });

        return $dispatcher;
    }

    /**
     * A store manager whose current store view is 5
     *
     * @return StoreManagerInterface
     */
    private function stores(): StoreManagerInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(5);
        $stores = $this->createMock(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return $stores;
    }
}
