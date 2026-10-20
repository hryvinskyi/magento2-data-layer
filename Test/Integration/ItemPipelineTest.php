<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration;

use Hryvinskyi\DataLayer\Model\ItemBuilder;
use Hryvinskyi\DataLayer\Test\Integration\Catalog\CatalogFixtures;
use Hryvinskyi\DataLayerApi\Api\Data\ItemInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterfaceFactory;
use Hryvinskyi\DataLayerApi\Api\ItemBuilderInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * The item pipeline wired by the container builds GA4 items for a real product and a real cart line, with the
 * providers in their registered order and the catalogue reads behind them
 *
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 * @magentoDataFixture Magento/Catalog/_files/categories_no_products.php
 */
class ItemPipelineTest extends TestCase
{
    use CatalogFixtures;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->cleanCategoryTrees();
    }

    /**
     * The builder interface resolves to the pipeline
     *
     * @return void
     */
    public function testBuilderResolvesToThePipeline(): void
    {
        self::assertInstanceOf(ItemBuilder::class, $this->service(ItemBuilderInterface::class));
    }

    /**
     * A product in a cart reports its SKU, its line's name, unit price and quantity, its category path and the store
     *
     * @return void
     */
    public function testCartLineItem(): void
    {
        $product = $this->sellableProduct('DL-PIPE-1', 'Pipeline laptop', 25.0);
        $this->assign((int) $product->getId(), [4]);

        $quote = $this->fresh(Quote::class);
        $quote->setStoreId(1);
        $quote->addProduct($product, 2);
        $quote->collectTotals();
        $line = $quote->getAllVisibleItems()[0] ?? null;
        self::assertInstanceOf(QuoteItem::class, $line);

        $items = $this->service(ItemBuilderInterface::class)->build(
            [$this->subject(['product' => $product, 'storeId' => 1, 'quantity' => 2.0, 'quoteItem' => $line])],
            'add_to_cart'
        );

        self::assertSame(
            [
                'item_id' => 'DL-PIPE-1',
                'item_name' => 'Pipeline laptop',
                'price' => 25.0,
                'quantity' => 2,
                'item_category' => 'Category 1',
                'item_category2' => 'Category 1.1',
                'affiliation' => $this->storeName(),
            ],
            $this->onlyItem($items)->getParameters()
        );
    }

    /**
     * A product shown in a category list reports its listing price, the list, and that category's path
     *
     * @return void
     */
    public function testCatalogueListItem(): void
    {
        $product = $this->sellableProduct('DL-PIPE-2', 'Pipeline mouse', 12.5);
        $this->assign((int) $product->getId(), [4, 5]);

        $items = $this->service(ItemBuilderInterface::class)->build(
            [$this->subject([
                'product' => $product,
                'storeId' => 1,
                'listId' => 'category_4',
                'listName' => 'Category 1.1',
                'index' => 0,
            ])],
            'view_item_list'
        );

        self::assertSame(
            [
                'item_id' => 'DL-PIPE-2',
                'item_name' => 'Pipeline mouse',
                'price' => 12.5,
                'quantity' => 1,
                'item_category' => 'Category 1',
                'item_category2' => 'Category 1.1',
                'item_list_id' => 'category_4',
                'item_list_name' => 'Category 1.1',
                'index' => 0,
            ],
            $this->onlyItem($items)->getParameters()
        );
    }

    /**
     * Create an enabled simple product that can be added to a cart without stock, and load it for store view 1
     *
     * @param string $sku
     * @param string $name
     * @param float $price
     * @return Product
     */
    private function sellableProduct(string $sku, string $name, float $price): Product
    {
        $product = $this->fresh(Product::class);
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setSku($sku)
            ->setName($name)
            ->setPrice($price)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setWebsiteIds([1])
            ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0]);
        $resource = $this->service(ProductResource::class);
        $resource->save($product);

        $loaded = $this->fresh(Product::class);
        $loaded->setStoreId(1);
        $resource->load($loaded, (int) $product->getId());

        return $loaded;
    }

    /**
     * Create an item subject through its generated factory
     *
     * @param array<string, mixed> $data
     * @return ItemSubjectInterface
     */
    private function subject(array $data): ItemSubjectInterface
    {
        return $this->service(ItemSubjectInterfaceFactory::class)->create($data);
    }

    /**
     * Get the frontend name of store view 1
     *
     * @return string
     */
    private function storeName(): string
    {
        $store = $this->service(StoreManagerInterface::class)->getStore(1);

        return $store instanceof Store ? $store->getFrontendName() : (string) $store->getName();
    }

    /**
     * Get the one item built
     *
     * @param list<ItemInterface> $items
     * @return ItemInterface
     */
    private function onlyItem(array $items): ItemInterface
    {
        self::assertCount(1, $items);

        return $items[0];
    }
}
