<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\AttributeValueReaderInterface;
use Hryvinskyi\DataLayer\Model\Catalog\AttributeValueReader;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Attribute values read from the real catalogue: static columns, value tables with the store view's value over the
 * default one, and nothing (logged once) for an attribute that cannot be read
 *
 * @magentoDbIsolation enabled
 */
class AttributeValueReaderTest extends TestCase
{
    use CatalogFixtures;

    private const STORE_ID = 1;

    /**
     * The reader port resolves to the batch reader
     *
     * @return void
     */
    public function testPortResolvesToTheBatchReader(): void
    {
        self::assertInstanceOf(AttributeValueReader::class, $this->service(AttributeValueReaderInterface::class));
    }

    /**
     * A static attribute comes from its column of the product table
     *
     * @return void
     */
    public function testStaticAttributeIsReadFromTheProductTable(): void
    {
        $first = $this->createProduct('dl-attr-first');
        $second = $this->createProduct('dl-attr-second');

        self::assertSame(
            [$first => 'dl-attr-first', $second => 'dl-attr-second'],
            $this->sorted($this->service(AttributeValueReaderInterface::class)->read(
                [$first, $second, $first],
                'sku',
                self::STORE_ID
            ))
        );
    }

    /**
     * A value-table attribute prefers the store view's value and falls back to the default one
     *
     * @return void
     */
    public function testStoreViewValueWinsOverDefault(): void
    {
        $first = $this->createProduct('dl-attr-store-first', 'Default first');
        $second = $this->createProduct('dl-attr-store-second', 'Default second');
        $this->storeViewName($first, 'Store first');
        $reader = $this->service(AttributeValueReaderInterface::class);

        self::assertSame(
            [$first => 'Store first', $second => 'Default second'],
            $this->sorted($reader->read([$first, $second], 'name', self::STORE_ID))
        );
        self::assertSame(
            [$first => 'Default first', $second => 'Default second'],
            $this->sorted($reader->read([$first, $second], 'name', 0))
        );
        self::assertSame(
            [$first => '1', $second => '1'],
            $this->sorted($reader->read([$first, $second], 'status', self::STORE_ID))
        );
    }

    /**
     * An attribute that does not exist yields nothing and is logged once per read
     *
     * @return void
     */
    public function testUnknownAttributeYieldsNothingAndIsLoggedOnce(): void
    {
        $first = $this->createProduct('dl-attr-unknown-first');
        $second = $this->createProduct('dl-attr-unknown-second');
        $logger = new class extends AbstractLogger {
            /**
             * @var list<string>
             */
            public array $messages = [];

            /**
             * @inheritDoc
             */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->messages[] = (string) $message;
            }
        };
        $reader = Bootstrap::getObjectManager()->create(AttributeValueReader::class, ['logger' => $logger]);
        self::assertInstanceOf(AttributeValueReader::class, $reader);

        self::assertSame([], $reader->read([$first, $second], 'dl_no_such_attribute', self::STORE_ID));
        self::assertCount(1, $logger->messages);
    }

    /**
     * Give a product a name of its own in the store view
     *
     * @param int $productId
     * @param string $name
     * @return void
     */
    private function storeViewName(int $productId, string $name): void
    {
        $resource = $this->service(ProductResource::class);
        $product = $this->fresh(Product::class);
        $product->setStoreId(self::STORE_ID);
        $resource->load($product, $productId);
        $product->setName($name);
        $resource->save($product);
    }

    /**
     * Sort values by product id
     *
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private function sorted(array $values): array
    {
        ksort($values);

        return $values;
    }
}
