<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration\Product;

use Hryvinskyi\DataLayer\Api\Product\CataloguePriceResolverInterface;
use Hryvinskyi\DataLayer\Test\Integration\ResolvesServices;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use PHPUnit\Framework\TestCase;

/**
 * A catalogue price wanted including tax carries the product's tax even when the store shows prices excluding tax,
 * calculated by Magento's tax calculation for the product's tax class and the store's default tax destination
 *
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 * @magentoDataFixture Magento/Tax/_files/tax_rule_region_1_al.php
 */
class CataloguePriceTaxTest extends TestCase
{
    use ResolvesServices;

    /**
     * A taxable product of 100 under a 7.5% rate reads 107.5 including tax and 100 excluding it, while the store
     * shows prices excluding tax
     *
     * @magentoConfigFixture current_store tax/defaults/country US
     * @magentoConfigFixture current_store tax/defaults/region 1
     * @magentoConfigFixture current_store tax/display/type 1
     * @magentoConfigFixture current_store tax/calculation/price_includes_tax 0
     * @return void
     */
    public function testPriceIncludingTaxWhileTheStoreShowsPricesExcludingTax(): void
    {
        $product = $this->taxableProduct('DL-TAX-1', 100.0);
        $prices = $this->service(CataloguePriceResolverInterface::class);

        self::assertSame(107.5, round((float) $prices->unitPrice($product, true), 2));
        self::assertSame(100.0, $prices->unitPrice($product, false));
    }

    /**
     * Create a taxable simple product and load it for store view 1
     *
     * @param string $sku
     * @param float $price
     * @return Product
     */
    private function taxableProduct(string $sku, float $price): Product
    {
        $product = $this->fresh(Product::class);
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setSku($sku)
            ->setName('Taxable ' . $sku)
            ->setPrice($price)
            ->setData('tax_class_id', 2)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setWebsiteIds([1]);
        $resource = $this->service(ProductResource::class);
        $resource->save($product);

        $loaded = $this->fresh(Product::class);
        $loaded->setStoreId(1);
        $resource->load($loaded, (int) $product->getId());

        return $loaded;
    }
}
