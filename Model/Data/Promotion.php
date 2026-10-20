<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Data;

use Hryvinskyi\DataLayerApi\Api\Data\PromotionInterface;

/**
 * One promotion as GA4 reports it, valid by construction
 *
 * A promotion is identified by a non-empty id or a non-empty name; one with neither is refused when it is created,
 * since nothing would tell its impressions and clicks apart from another's.
 */
class Promotion implements PromotionInterface
{
    /**
     * @param string $promotionId
     * @param string $promotionName
     * @param string $creativeName
     * @param string $creativeSlot
     * @param string $locationId
     * @param string $targetUrl The link the promotion opens; empty when it links nowhere
     * @throws \InvalidArgumentException When neither an id nor a name is given
     */
    public function __construct(
        private readonly string $promotionId = '',
        private readonly string $promotionName = '',
        private readonly string $creativeName = '',
        private readonly string $creativeSlot = '',
        private readonly string $locationId = '',
        private readonly string $targetUrl = ''
    ) {
        if (trim($promotionId) === '' && trim($promotionName) === '') {
            throw new \InvalidArgumentException('A GA4 promotion needs a non-empty id or name.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getPromotionId(): string
    {
        return $this->promotionId;
    }

    /**
     * @inheritDoc
     */
    public function getPromotionName(): string
    {
        return $this->promotionName;
    }

    /**
     * @inheritDoc
     */
    public function getCreativeName(): string
    {
        return $this->creativeName;
    }

    /**
     * @inheritDoc
     */
    public function getCreativeSlot(): string
    {
        return $this->creativeSlot;
    }

    /**
     * @inheritDoc
     */
    public function getLocationId(): string
    {
        return $this->locationId;
    }

    /**
     * @inheritDoc
     */
    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    /**
     * @inheritDoc
     */
    public function toParameters(): array
    {
        $parameters = [
            'promotion_id' => $this->promotionId,
            'promotion_name' => $this->promotionName,
            'creative_name' => $this->creativeName,
            'creative_slot' => $this->creativeSlot,
            'location_id' => $this->locationId,
        ];

        return array_filter($parameters, static fn (string $value): bool => $value !== '');
    }
}
