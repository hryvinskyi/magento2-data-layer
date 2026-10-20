<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\PageEvent;

use Hryvinskyi\DataLayer\Api\Event\OrderEventBuilderInterface;
use Hryvinskyi\DataLayer\Api\Event\PurchaseEventBuilderInterface;
use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Model\PageEvent\PurchasePageEvents;
use Hryvinskyi\DataLayer\Model\PurchaseLedger;
use Hryvinskyi\DataLayer\Model\Session\VisitorSession;
use Hryvinskyi\DataLayer\Test\Unit\Model\FakeSession;
use Hryvinskyi\DataLayerApi\Api\Data\EventChannel;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\EventChannelPolicyInterface;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;

/**
 * A success page prints each order once per session, and nothing when `purchase` goes to the server only; the
 * conversion that follows it keeps its own ledger and channel
 */
class PurchasePageEventsTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $built = [];

    /**
     * The first page reports the order; a reload of it reports nothing
     *
     * @return void
     */
    public function testReportsOnceAndNotOnReload(): void
    {
        $session = new FakeSession();
        $order = $this->order('100');

        self::assertSame(['100'], $this->transactionIds($this->purchases($session)->forOrders([$order])));
        self::assertSame([], $this->purchases($session)->forOrders([$order]));
        self::assertSame(['100'], $this->built, 'the reload does not even build the event');
    }

    /**
     * Each order is reported on its own; one already in the ledger is not
     *
     * @return void
     */
    public function testOnePerOrderExceptReportedOnes(): void
    {
        $session = new FakeSession();
        (new PurchaseLedger(new VisitorSession($session)))->markReported('101');

        $orders = [$this->order('100'), $this->order('101'), $this->order('102')];
        $events = $this->purchases($session)->forOrders($orders);

        self::assertSame(['100', '102'], $this->transactionIds($events));
    }

    /**
     * Routed to the server only, nothing is printed and nothing is remembered; routed to both, it is printed
     *
     * @return void
     */
    public function testChannel(): void
    {
        $session = new FakeSession();
        $order = $this->order('100');

        self::assertSame([], $this->purchases($session, EventChannel::Server)->forOrders([$order]));
        self::assertFalse((new PurchaseLedger(new VisitorSession($session)))->wasReported('100'));
        self::assertSame(['100'], $this->transactionIds(
            $this->purchases($session, EventChannel::Both)->forOrders([$order])
        ));
    }

    /**
     * An order that builds no event (disabled at the time) is not remembered as reported
     *
     * @return void
     */
    public function testUnbuiltOrderIsNotRemembered(): void
    {
        $session = new FakeSession();

        $purchases = $this->purchases($session, EventChannel::Browser, false);
        self::assertSame([], $purchases->forOrders([$this->order('100')]));
        self::assertFalse((new PurchaseLedger(new VisitorSession($session)))->wasReported('100'));
    }

    /**
     * The conversion has a ledger of its own: reporting the purchase does not mark the conversion and the other way
     * round, and a reload reports neither; its channel is asked under its own name
     *
     * @return void
     */
    public function testConversionKeepsItsOwnLedgerAndChannel(): void
    {
        $session = new FakeSession();
        $order = $this->order('100');
        $askedChannels = [];
        $channels = $this->createMock(EventChannelPolicyInterface::class);
        $channels->method('channelOf')->willReturnCallback(
            static function (string $eventName) use (&$askedChannels): EventChannel {
                $askedChannels[] = $eventName;

                return EventChannel::Browser;
            }
        );
        $builder = $this->createMock(OrderEventBuilderInterface::class);
        $builder->method('build')->willReturnCallback(
            static fn (OrderInterface $order): EventInterface
                => new Event('ads_conversion', false, ['transaction_id' => (string) $order->getIncrementId()])
        );
        $conversions = new PurchasePageEvents(
            $builder,
            new PurchaseLedger(new VisitorSession($session), 'reported_ads_conversions'),
            $channels,
            'ads_conversion'
        );

        self::assertSame(['100'], $this->transactionIds($this->purchases($session)->forOrders([$order])));
        self::assertSame(['100'], $this->transactionIds($conversions->forOrders([$order])));
        self::assertSame([], $conversions->forOrders([$order]));
        self::assertSame([], $this->purchases($session)->forOrders([$order]));
        self::assertSame(['ads_conversion', 'ads_conversion'], $askedChannels);
        self::assertSame(['100'], $session->getData('reported_purchases'));
        self::assertSame(['100'], $session->getData('reported_ads_conversions'));
    }

    /**
     * The service over a session, with a builder that reports each order's increment id
     *
     * @param FakeSession $session
     * @param EventChannel $channel
     * @param bool $builds False for a builder that builds nothing
     * @return PurchasePageEvents
     */
    private function purchases(
        FakeSession $session,
        EventChannel $channel = EventChannel::Browser,
        bool $builds = true
    ): PurchasePageEvents {
        $builder = $this->createMock(PurchaseEventBuilderInterface::class);
        $builder->method('build')->willReturnCallback(
            function (OrderInterface $order) use ($builds): ?EventInterface {
                $this->built[] = (string) $order->getIncrementId();

                return $builds
                    ? new Event('purchase', true, ['transaction_id' => (string) $order->getIncrementId()])
                    : null;
            }
        );
        $channels = $this->createMock(EventChannelPolicyInterface::class);
        $channels->method('channelOf')->willReturn($channel);

        return new PurchasePageEvents($builder, new PurchaseLedger(new VisitorSession($session)), $channels);
    }

    /**
     * An order
     *
     * @param string $incrementId
     * @return OrderInterface
     */
    private function order(string $incrementId): OrderInterface
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn($incrementId);
        $order->method('getStoreId')->willReturn(1);

        return $order;
    }

    /**
     * The transaction ids of events
     *
     * @param list<EventInterface> $events
     * @return list<string>
     */
    private function transactionIds(array $events): array
    {
        return array_map(
            static fn (EventInterface $event): string => (string) ($event->getParameters()['transaction_id'] ?? ''),
            $events
        );
    }
}
