<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\ItemField;

use Hryvinskyi\DataLayer\Api\ConfigInterface;
use Hryvinskyi\DataLayer\Api\ItemField\LineFiguresInterface;
use Hryvinskyi\DataLayer\Api\Product\CataloguePriceResolverInterface;
use Hryvinskyi\DataLayerApi\Api\Data\ItemSubjectInterface;
use Hryvinskyi\DataLayerApi\Api\ItemFieldProviderInterface;

/**
 * Fills `price`, rounded to two decimals, on the store's tax basis for the kind of item (excluding tax unless it says
 * otherwise)
 *
 * An item from a cart or order line reports the line's unit price on the line basis; any other item the price its
 * catalogue listing shows, by the product's type, on the catalogue basis. A cart line whose totals have not been
 * collected yet (one an addition just created or raised) reports the catalogue price of what it sells instead: the
 * chosen child of a configurable line, the line's product otherwise, at the tier price for the line's whole quantity;
 * it is still a line, so that price is on the line basis. An item whose price is unknown reports none.
 */
class PriceProvider implements ItemFieldProviderInterface
{
    /**
     * @param ConfigInterface $config
     * @param LineFiguresInterface $lineFigures
     * @param CataloguePriceResolverInterface $cataloguePrices
     */
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LineFiguresInterface $lineFigures,
        private readonly CataloguePriceResolverInterface $cataloguePrices
    ) {
    }

    /**
     * @inheritDoc
     */
    public function provide(array $subjects, string $eventName): array
    {
        $catalogueIncludingTax = [];
        $lineIncludingTax = [];
        $fields = [];
        foreach ($subjects as $key => $subject) {
            $storeId = $subject->getStoreId();
            if ($subject->getOrderItem() === null && $subject->getQuoteItem() === null) {
                $catalogueIncludingTax[$storeId] ??= $this->config->isCataloguePriceIncludingTax($storeId);
                $price = $this->cataloguePrices->unitPrice($subject->getProduct(), $catalogueIncludingTax[$storeId]);
            } else {
                $lineIncludingTax[$storeId] ??= $this->config->isLinePriceIncludingTax($storeId);
                $price = $this->linePrice($subject, $lineIncludingTax[$storeId]);
            }
            if ($price !== null) {
                $fields[$key] = ['price' => round($price, 2)];
            }
        }

        return $fields;
    }

    /**
     * Get the unit price of an item from a cart or order line
     *
     * @param ItemSubjectInterface $subject
     * @param bool $includingTax The line basis
     * @return float|null
     */
    private function linePrice(ItemSubjectInterface $subject, bool $includingTax): ?float
    {
        if ($subject->getOrderItem() !== null) {
            return $this->lineFigures->unitPrice($subject, $includingTax);
        }

        return $this->lineFigures->unitPrice($subject, $includingTax)
            ?? $this->cataloguePrices->unitPrice(
                $subject->getChildProduct() ?? $subject->getProduct(),
                $includingTax,
                $this->lineQuantity($subject)
            );
    }

    /**
     * Get the quantity of the cart line an item comes from, which its tier price depends on
     *
     * @param ItemSubjectInterface $subject
     * @return float One when the line stores no positive quantity
     */
    private function lineQuantity(ItemSubjectInterface $subject): float
    {
        $quantity = $subject->getQuoteItem()?->getQty();

        return is_numeric($quantity) && (float) $quantity > 0.0 ? (float) $quantity : 1.0;
    }
}
