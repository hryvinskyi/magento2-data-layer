<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Unit\Model\ItemField;

use Hryvinskyi\DataLayer\Model\ItemField\IdentityProvider;
use Hryvinskyi\DataLayer\Model\ItemField\ProductAttributeValues;
use PHPUnit\Framework\TestCase;

/**
 * `item_id` is the configured identifier of the catalogue product, `item_name` the line's name or the product's
 */
class IdentityProviderTest extends TestCase
{
    use SubjectDoubles;

    /**
     * The identifier defaults to the SKU; a line reports its own name, a catalogue item its product's
     *
     * @return void
     */
    public function testSkuAndNamesForCatalogueAndLineItems(): void
    {
        $reader = new FakeAttributeValueReader();
        $provider = new IdentityProvider($this->settings(), new ProductAttributeValues($reader));

        $fields = $provider->provide(
            [
                $this->subject($this->product(1, 'Laptop', ['sku' => 'LAP-1'])),
                $this->subject(
                    $this->product(2, 'Shirt', ['sku' => 'SHIRT']),
                    ['cart' => $this->cartLine(['name' => 'Shirt (red)'])]
                ),
                $this->subject(
                    $this->product(3, 'Mouse', ['sku' => 'MOUSE']),
                    ['order' => $this->orderLine(['name' => 'Mouse, ordered'])]
                ),
                $this->subject($this->product(4, 'Cable', ['sku' => 'CABLE']), ['cart' => $this->cartLine()]),
            ],
            'view_item'
        );

        self::assertSame(
            [
                0 => ['item_id' => 'LAP-1', 'item_name' => 'Laptop'],
                1 => ['item_id' => 'SHIRT', 'item_name' => 'Shirt (red)'],
                2 => ['item_id' => 'MOUSE', 'item_name' => 'Mouse, ordered'],
                3 => ['item_id' => 'CABLE', 'item_name' => 'Cable'],
            ],
            $fields
        );
        self::assertSame([], $reader->calls);
    }

    /**
     * Identifiers missing from the loaded products are read for all of them in one call
     *
     * @return void
     */
    public function testMissingIdentifiersAreReadOnceForAllProducts(): void
    {
        $reader = new FakeAttributeValueReader(['mpn' => [1 => 'M1', 2 => 'M2']]);
        $provider = new IdentityProvider(
            $this->settings(['hryvinskyi_datalayer/item/identifier_attribute' => 'mpn']),
            new ProductAttributeValues($reader)
        );

        $fields = $provider->provide(
            [
                $this->subject($this->product(1, 'A')),
                $this->subject($this->product(2, 'B')),
                $this->subject($this->product(1, 'A'), ['listId' => 'related']),
            ],
            'view_item_list'
        );

        self::assertSame('M1', $fields[0]['item_id']);
        self::assertSame('M2', $fields[1]['item_id']);
        self::assertSame('M1', $fields[2]['item_id']);
        self::assertCount(1, $reader->calls);
        self::assertSame([1, 2], $reader->calls[0]['productIds']);
    }

    /**
     * The product id is reported for `entity_id`
     *
     * @return void
     */
    public function testProductIdAsIdentifier(): void
    {
        $provider = new IdentityProvider(
            $this->settings(['hryvinskyi_datalayer/item/identifier_attribute' => 'entity_id']),
            new ProductAttributeValues(new FakeAttributeValueReader())
        );

        self::assertSame(
            [0 => ['item_id' => '15', 'item_name' => 'Desk']],
            $provider->provide([$this->subject($this->product(15, 'Desk'))], 'view_item')
        );
    }
}
