<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\Event;

use Hryvinskyi\DataLayer\Api\Event\EcommerceEventAssemblerInterface;
use Hryvinskyi\DataLayer\Model\Data\Event;
use Hryvinskyi\DataLayer\Model\Event\AddToCartEventBuilder;
use Hryvinskyi\DataLayer\Model\Event\AddToWishlistEventBuilder;
use Hryvinskyi\DataLayer\Model\Event\LoginEventBuilder;
use Hryvinskyi\DataLayer\Model\Event\RemoveFromCartEventBuilder;
use Hryvinskyi\DataLayer\Model\Event\SignUpEventBuilder;
use Hryvinskyi\DataLayerApi\Api\EventAvailabilityInterface;
use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use PHPUnit\Framework\TestCase;

/**
 * Account events are plain events with `method` = `website`, built only when enabled; the cart line and wish list
 * builders ask for their own event name
 */
class ServerEventBuildersTest extends TestCase
{
    use ServerEventDoubles;

    /**
     * Sign-in and sign-up report the store's own account as the method, and nothing when disabled
     *
     * @return void
     */
    public function testAccountEvents(): void
    {
        $enabled = $this->createMock(EventAvailabilityInterface::class);
        $enabled->method('isEnabled')->willReturnCallback(
            static fn (string $name, ?int $storeId): bool => $storeId === 2
        );

        self::assertSame(
            ['event' => 'login', 'method' => 'website'],
            $this->built((new LoginEventBuilder($enabled, $this->eventFactory()))->build(2))->toDataLayer()
        );
        self::assertSame(
            ['event' => 'sign_up', 'method' => 'website'],
            $this->built((new SignUpEventBuilder($enabled, $this->eventFactory()))->build(2))->toDataLayer()
        );
        self::assertNull((new LoginEventBuilder($enabled, $this->eventFactory()))->build(1));
        self::assertNull((new SignUpEventBuilder($enabled, $this->eventFactory()))->build(1));
    }

    /**
     * The cart line and wish list builders name their event
     *
     * @return void
     */
    public function testLineAndWishlistBuildersNameTheirEvent(): void
    {
        $asked = [];
        $assembler = $this->createMock(EcommerceEventAssemblerInterface::class);
        $assembler->method('forCartLine')->willReturnCallback(
            static function (string $name, mixed $line, float $quantity) use (&$asked): Event {
                $asked[] = [$name, $quantity];

                return new Event($name, true);
            }
        );
        $assembler->method('forProduct')->willReturnCallback(
            static function (string $name, mixed $product, float $quantity, int $storeId) use (&$asked): Event {
                $asked[] = [$name, $quantity, $storeId];

                return new Event($name, true);
            }
        );
        $line = $this->createMock(QuoteItem::class);

        (new AddToCartEventBuilder($assembler))->build($line, 2.0);
        (new RemoveFromCartEventBuilder($assembler))->build($line, 1.0);
        (new AddToWishlistEventBuilder($assembler))->build($this->createMock(Product::class), 3.0, 4);

        self::assertSame(
            [['add_to_cart', 2.0], ['remove_from_cart', 1.0], ['add_to_wishlist', 3.0, 4]],
            $asked
        );
    }
}
