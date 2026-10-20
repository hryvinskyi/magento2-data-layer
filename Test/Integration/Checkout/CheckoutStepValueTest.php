<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration\Checkout;

use Hryvinskyi\DataLayer\Model\Event\AddPaymentInfoEventBuilder;
use Hryvinskyi\DataLayer\Model\Event\AddShippingInfoEventBuilder;
use Hryvinskyi\DataLayer\Model\Event\CartEventBuilder;
use Hryvinskyi\DataLayer\Test\Integration\ResolvesServices;
use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

/**
 * The checkout steps built by the container report the items' value by default, like every other cart event, and the
 * cart's grand total when the store's website asks for it; the other cart events keep the items' value either way
 *
 * The cart holds two units of a taxable 30.00 product shipped to an address taxed at 7.5%: its items are worth 60.00
 * excluding tax (the default line basis) and its grand total, tax included, is 64.50.
 *
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 * @magentoDataFixture Magento/Tax/_files/tax_rule_region_1_al.php
 */
class CheckoutStepValueTest extends TestCase
{
    use ResolvesServices;

    private const ENABLED = 'hryvinskyi_datalayer/general/enabled';
    private const STEP_VALUE_BASIS = 'hryvinskyi_datalayer/checkout/step_value_basis';

    /**
     * By default both steps report the items' value, without tax
     *
     * @magentoConfigFixture current_store hryvinskyi_datalayer/general/enabled 1
     * @return void
     */
    public function testStepsReportTheItemsValueByDefault(): void
    {
        $cart = $this->shippedCart('DL-STEP-1');

        $grandTotal = $cart->getData('grand_total');
        self::assertSame(64.5, is_numeric($grandTotal) ? (float) $grandTotal : null);
        self::assertSame(60.0, $this->value($this->service(AddShippingInfoEventBuilder::class)->build($cart)));
        self::assertSame(60.0, $this->value($this->service(AddPaymentInfoEventBuilder::class)->build($cart)));
    }

    /**
     * A basis of grand total stored for the website, as a website-level setting is saved, reaches its store view:
     * both steps report the grand total, while other cart events keep the items' value
     *
     * @return void
     */
    public function testWebsiteGrandTotalBasisAppliesToTheStepsOnly(): void
    {
        $cart = $this->shippedCart('DL-STEP-2');
        $writer = $this->service(WriterInterface::class);
        $writer->save(self::ENABLED, '1', ScopeInterface::SCOPE_WEBSITES, 1);
        $writer->save(self::STEP_VALUE_BASIS, 'grand_total', ScopeInterface::SCOPE_WEBSITES, 1);
        $this->service(ReinitableConfigInterface::class)->reinit();

        try {
            self::assertSame(64.5, $this->value($this->service(AddShippingInfoEventBuilder::class)->build($cart)));
            self::assertSame(64.5, $this->value($this->service(AddPaymentInfoEventBuilder::class)->build($cart)));
            self::assertSame(
                60.0,
                $this->value($this->service(CartEventBuilder::class, 'HryvinskyiDataLayerBeginCheckoutEventBuilder')
                    ->build($cart))
            );
        } finally {
            $writer->delete(self::ENABLED, ScopeInterface::SCOPE_WEBSITES, 1);
            $writer->delete(self::STEP_VALUE_BASIS, ScopeInterface::SCOPE_WEBSITES, 1);
            $this->service(ReinitableConfigInterface::class)->reinit();
        }
    }

    /**
     * Read an event's value
     *
     * @param EventInterface|null $event
     * @return float|null Null when the event carries none
     */
    private function value(?EventInterface $event): ?float
    {
        self::assertNotNull($event);
        $value = $event->getParameters()['value'] ?? null;

        return is_int($value) || is_float($value) ? (float) $value : null;
    }

    /**
     * A cart of two units of a new taxable 30.00 product, shipped to an address in the taxed region, with collected
     * totals
     *
     * @param string $sku
     * @return Quote
     */
    private function shippedCart(string $sku): Quote
    {
        $product = $this->fresh(Product::class);
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setSku($sku)
            ->setName('Checkout step ' . $sku)
            ->setPrice(30.0)
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

        $cart = $this->fresh(Quote::class);
        $cart->setStoreId(1);
        $cart->setData('quote_currency_code', 'USD');
        $cart->addProduct($loaded, 2);
        $address = $cart->getShippingAddress();
        $address->addData([
            'firstname' => 'Test',
            'lastname' => 'Shopper',
            'street' => '1 Test Street',
            'city' => 'Birmingham',
            'region_id' => 1,
            'postcode' => '35004',
            'country_id' => 'US',
            'telephone' => '5555555',
        ]);
        $cart->collectTotals();

        return $cart;
    }
}
