<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Test\Integration\Catalog;

use Hryvinskyi\DataLayer\Api\Catalog\OptionLabelReaderInterface;
use Hryvinskyi\DataLayer\Model\Catalog\OptionLabelReader;
use Hryvinskyi\DataLayer\Test\Integration\ResolvesServices;
use Magento\Framework\App\ResourceConnection;
use PHPUnit\Framework\TestCase;

/**
 * Option labels read from the real catalogue: the store view's label over the default one, only options of the
 * named attribute
 *
 * @magentoDbIsolation enabled
 * @magentoDataFixture Magento/Catalog/_files/dropdown_attribute.php
 */
class OptionLabelReaderTest extends TestCase
{
    use ResolvesServices;

    private const STORE_ID = 1;

    /**
     * The reader port resolves to the batch reader
     *
     * @return void
     */
    public function testPortResolvesToTheBatchReader(): void
    {
        self::assertInstanceOf(OptionLabelReader::class, $this->reader());
    }

    /**
     * The store view's label wins over the default one; the default label serves the rest
     *
     * @return void
     */
    public function testStoreViewLabelWinsOverDefault(): void
    {
        $options = $this->options();
        $resource = $this->service(ResourceConnection::class);
        $resource->getConnection()->insert(
            $resource->getTableName('eav_attribute_option_value'),
            ['option_id' => $options['Option 1'], 'store_id' => self::STORE_ID, 'value' => 'Store option 1']
        );
        $requested = [$options['Option 1'], (string) $options['Option 2'], $options['Option 3'], 999999999];

        $inStore = $this->reader()->labels('dropdown_attribute', $requested, self::STORE_ID);
        ksort($inStore);
        $byDefault = $this->reader()->labels('dropdown_attribute', $requested, 0);
        ksort($byDefault);

        self::assertSame(
            [
                $options['Option 1'] => 'Store option 1',
                $options['Option 2'] => 'Option 2',
                $options['Option 3'] => 'Option 3',
            ],
            $inStore
        );
        self::assertSame(
            [
                $options['Option 1'] => 'Option 1',
                $options['Option 2'] => 'Option 2',
                $options['Option 3'] => 'Option 3',
            ],
            $byDefault
        );
    }

    /**
     * Options are read only for the attribute named; an unknown attribute yields nothing
     *
     * @return void
     */
    public function testOnlyTheNamedAttributesOptionsAreRead(): void
    {
        $optionIds = array_values($this->options());

        self::assertSame([], $this->reader()->labels('status', $optionIds, self::STORE_ID));
        self::assertSame([], $this->reader()->labels('dl_no_such_attribute', $optionIds, self::STORE_ID));
    }

    /**
     * Get the fixture attribute's options, default label to option id
     *
     * @return array<string, int>
     */
    private function options(): array
    {
        $resource = $this->service(ResourceConnection::class);
        $connection = $resource->getConnection();
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from(['o' => $resource->getTableName('eav_attribute_option')], [])
                ->join(['a' => $resource->getTableName('eav_attribute')], 'a.attribute_id = o.attribute_id', [])
                ->join(
                    ['v' => $resource->getTableName('eav_attribute_option_value')],
                    'v.option_id = o.option_id AND v.store_id = 0',
                    ['value', 'option_id']
                )
                ->where('a.attribute_code = ?', 'dropdown_attribute')
        );
        $options = [];
        foreach ($rows as $label => $optionId) {
            $options[(string) $label] = is_numeric($optionId) ? (int) $optionId : 0;
        }
        self::assertCount(3, $options);

        return $options;
    }

    /**
     * Get the reader through the container
     *
     * @return OptionLabelReaderInterface
     */
    private function reader(): OptionLabelReaderInterface
    {
        return $this->service(OptionLabelReaderInterface::class);
    }
}
