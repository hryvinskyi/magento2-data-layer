<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration\Product;

use Hryvinskyi\DataLayer\Test\Integration\ResolvesServices;
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
use PHPUnit\Framework\TestCase;

/**
 * The item pipeline wired by the container prices a catalogue item on the catalogue tax basis and a cart line on the
 * line tax basis, each from its own setting; a cart line whose totals have not run reports its product's catalogue
 * price, on the line basis because it is a line
 *
 * A taxable product of 100.00 under a 7.5% rate, in a store that shows prices excluding tax.
 *
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 * @magentoDataFixture Magento/Tax/_files/tax_rule_region_1_al.php
 */
class PriceTaxBasesTest extends TestCase
{
    use ResolvesServices;

    /**
     * Catalogue prices including tax, line prices excluding it
     *
     * @magentoConfigFixture current_store tax/defaults/country US
     * @magentoConfigFixture current_store tax/defaults/region 1
     * @magentoConfigFixture current_store tax/display/type 1
     * @magentoConfigFixture current_store tax/calculation/price_includes_tax 0
     * @magentoConfigFixture current_store hryvinskyi_datalayer/item/catalogue_price_includes_tax 1
     * @magentoConfigFixture current_store hryvinskyi_datalayer/item/line_price_includes_tax 0
     * @return void
     */
    public function testCatalogueIncludingTaxAndLinesExcludingTax(): void
    {
        $product = $this->taxableProduct('DL-BASIS-1');

        self::assertSame([107.5, 100.0], $this->prices($product));
    }

    /**
     * Catalogue prices excluding tax, line prices including it
     *
     * @magentoConfigFixture current_store tax/defaults/country US
     * @magentoConfigFixture current_store tax/defaults/region 1
     * @magentoConfigFixture current_store tax/display/type 1
     * @magentoConfigFixture current_store tax/calculation/price_includes_tax 0
     * @magentoConfigFixture current_store hryvinskyi_datalayer/item/catalogue_price_includes_tax 0
     * @magentoConfigFixture current_store hryvinskyi_datalayer/item/line_price_includes_tax 1
     * @return void
     */
    public function testCatalogueExcludingTaxAndLinesIncludingTax(): void
    {
        $product = $this->taxableProduct('DL-BASIS-2');

        self::assertSame([100.0, 107.5], $this->prices($product));
    }

    /**
     * Price the product as a catalogue item and as a cart line its addition has just created
     *
     * @param Product $product
     * @return array{float|null, float|null} The catalogue item's price, then the cart line's
     */
    private function prices(Product $product): array
    {
        $cart = $this->fresh(Quote::class);
        $cart->setStoreId(1);
        $cart->addProduct($product, 1);
        $line = $cart->getAllVisibleItems()[0] ?? null;
        self::assertInstanceOf(QuoteItem::class, $line);

        $items = $this->service(ItemBuilderInterface::class)->build(
            [
                $this->subject(['product' => $product, 'storeId' => 1]),
                $this->subject(['product' => $product, 'storeId' => 1, 'quantity' => 1.0, 'quoteItem' => $line]),
            ],
            'add_to_cart'
        );
        self::assertCount(2, $items);

        return [$this->price($items[0]->getParameters()), $this->price($items[1]->getParameters())];
    }

    /**
     * Read an item's price
     *
     * @param array<string, mixed> $parameters
     * @return float|null
     */
    private function price(array $parameters): ?float
    {
        $price = $parameters['price'] ?? null;

        return is_int($price) || is_float($price) ? (float) $price : null;
    }

    /**
     * Build an item subject through the container
     *
     * @param array<string, mixed> $data
     * @return ItemSubjectInterface
     */
    private function subject(array $data): ItemSubjectInterface
    {
        return $this->service(ItemSubjectInterfaceFactory::class)->create($data);
    }

    /**
     * Create a taxable simple product of 100.00 that can be added to a cart without stock, loaded for store view 1
     *
     * @param string $sku
     * @return Product
     */
    private function taxableProduct(string $sku): Product
    {
        $product = $this->fresh(Product::class);
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setSku($sku)
            ->setName('Taxable ' . $sku)
            ->setPrice(100.0)
            ->setData('tax_class_id', 2)
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
}
