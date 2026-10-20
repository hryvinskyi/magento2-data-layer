<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Cart;

use Hryvinskyi\DataLayer\Api\Event\CartLineEventBuilderInterface;
use Hryvinskyi\DataLayer\Model\Cart\CartQuantityChangeReporter;
use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Test\Unit\Model\RecordingLogger;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Hryvinskyi\DataLayerApi\Api\EventDispatcherInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use PHPUnit\Framework\TestCase;

/**
 * Units added go to the add-to-cart builder, units removed to the remove-from-cart builder, both as a positive number
 * of units, and the event is dispatched for the cart's store view; nothing escapes
 */
class CartQuantityChangeReporterTest extends TestCase
{
    /**
     * @var list<array{string, float}>
     */
    private array $built = [];

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
     * An increase is reported as added units, a decrease as removed units, no change as nothing
     *
     * @return void
     */
    public function testChangeGoesToTheRightBuilder(): void
    {
        $reporter = $this->reporter();
        $line = $this->line();

        $reporter->report($line, 2.0);
        $reporter->report($line, -3.0);
        $reporter->report($line, 0.0);
        $reporter->report($line, 0.00001);

        self::assertSame([['add_to_cart', 2.0], ['remove_from_cart', 3.0]], $this->built);
        self::assertSame([['add_to_cart', 3], ['remove_from_cart', 3]], $this->dispatched);
    }

    /**
     * A builder that fails is logged and the cart action goes on
     *
     * @return void
     */
    public function testFailureIsLogged(): void
    {
        $failing = $this->createMock(CartLineEventBuilderInterface::class);
        $failing->method('build')->willThrowException(new \RuntimeException('broken'));
        $reporter = new CartQuantityChangeReporter($failing, $failing, $this->dispatcher(), $this->logger);

        $reporter->report($this->line(), 1.0);

        self::assertSame([], $this->dispatched);
        self::assertSame('error', $this->logger->records[0]['level']);
        self::assertSame(1.0, $this->logger->records[0]['context']['change']);
    }

    /**
     * The reporter with recording builders
     *
     * @return CartQuantityChangeReporter
     */
    private function reporter(): CartQuantityChangeReporter
    {
        return new CartQuantityChangeReporter(
            $this->builder('add_to_cart'),
            $this->builder('remove_from_cart'),
            $this->dispatcher(),
            $this->logger
        );
    }

    /**
     * A builder that records the units it is asked about
     *
     * @param string $eventName
     * @return CartLineEventBuilderInterface
     */
    private function builder(string $eventName): CartLineEventBuilderInterface
    {
        $builder = $this->createMock(CartLineEventBuilderInterface::class);
        $builder->method('build')->willReturnCallback(
            function (mixed $line, float $quantity) use ($eventName): Event {
                $this->built[] = [$eventName, $quantity];

                return new Event($eventName, true);
            }
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
        $dispatcher->method('dispatch')->willReturnCallback(
            function (EventInterface $event, int $storeId): void {
                $this->dispatched[] = [$event->getName(), $storeId];
            }
        );

        return $dispatcher;
    }

    /**
     * A cart line of store view 3
     *
     * @return QuoteItem
     */
    private function line(): QuoteItem
    {
        $cart = $this->createMock(Quote::class);
        $cart->method('getStoreId')->willReturn(3);
        $line = $this->createMock(QuoteItem::class);
        $line->method('getQuote')->willReturn($cart);

        return $line;
    }
}
