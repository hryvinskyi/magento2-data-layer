<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration\Lists;

use Hryvinskyi\DataLayer\Api\Catalog\CategoryTreeInvalidatorInterface;
use Hryvinskyi\DataLayer\Test\Integration\ResolvesServices;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\App\ResourceConnection;

/**
 * Products for the list tests, saved through the resource model, and a way to count the queries a piece of work runs
 */
trait ListFixtures
{
    use ResolvesServices;

    /**
     * Drop every stored category tree, so no test reads a tree built from another test's data
     *
     * @return void
     */
    private function cleanCategoryTrees(): void
    {
        $this->service(CategoryTreeInvalidatorInterface::class)->invalidate();
    }

    /**
     * Create an enabled, visible, in-stock simple product in the main website
     *
     * @param string $sku
     * @param float $price
     * @return int The product id
     */
    private function createListedProduct(string $sku, float $price = 10.0): int
    {
        $product = $this->fresh(Product::class);
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setSku($sku)
            ->setName('Listed ' . $sku)
            ->setUrlKey($sku)
            ->setPrice($price)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setWebsiteIds([1])
            ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_qty_decimal' => 0, 'is_in_stock' => 1]);
        $this->service(ProductResource::class)->save($product);

        return (int) $product->getId();
    }

    /**
     * Count the database queries a piece of work issues
     *
     * @param callable(): mixed $work
     * @return list<string> The SQL of every query, in order
     */
    private function queriesOf(callable $work): array
    {
        $connection = $this->service(ResourceConnection::class)->getConnection();
        self::assertInstanceOf(\Zend_Db_Adapter_Abstract::class, $connection);
        $profiler = $connection->getProfiler();
        $profiler->setEnabled(true);
        $profiler->clear();
        try {
            $work();
            $queries = [];
            foreach ($profiler->getQueryProfiles() ?: [] as $profile) {
                self::assertInstanceOf(\Zend_Db_Profiler_Query::class, $profile);
                $queries[] = $profile->getQuery();
            }

            return $queries;
        } finally {
            $profiler->setEnabled(false);
        }
    }

    /**
     * Get the decoded data of the one list script a rendered list ends with
     *
     * Another module may wrap the whole list, script included, in an element of its own after the data is appended;
     * the script is then the last child of that element.
     *
     * @param string $html
     * @return array<mixed>
     */
    private function listDataOf(string $html): array
    {
        $opening = '~<script(?: data-hryvinskyi-datalayer-list="[0-9a-f]+")? type="application/json" '
            . 'data-hryvinskyi-datalayer="list">~';
        self::assertSame(
            1,
            preg_match_all($opening, $html, $found, PREG_OFFSET_CAPTURE),
            "The list carries exactly one data script. The end of the HTML:\n" . substr($html, -1500)
        );
        [$prefix, $offset] = $found[0][0];
        $script = substr($html, $offset);
        self::assertSame(
            1,
            preg_match('~^(.*?)</script>(\s*</[a-z]+>)*\s*$~s', $script, $match),
            'Nothing but the closing tags of an element enclosing the whole list follows the data script.'
        );
        $json = substr($match[1], strlen($prefix));
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }
}
