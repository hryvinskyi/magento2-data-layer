<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Data;

use Hryvinskyi\DataLayerApi\Api\Data\VisitorContextInterface;

/**
 * Who an event is about and when it happened, immutable
 */
class VisitorContext implements VisitorContextInterface
{
    /**
     * @var array<string, string>
     */
    private readonly array $clickIds;

    /**
     * @param \DateTimeImmutable $occurredAt The moment the event happened
     * @param string|null $clientId
     * @param string|null $sessionId
     * @param string|null $userId
     * @param array<mixed> $clickIds Advertising click ids keyed by name, for example `gclid`
     * @param string|null $userAgent
     * @param string|null $pageLocation
     * @param string|null $pageReferrer
     * @throws \InvalidArgumentException When a click id or its name is not a string
     */
    public function __construct(
        private readonly \DateTimeImmutable $occurredAt,
        private readonly ?string $clientId = null,
        private readonly ?string $sessionId = null,
        private readonly ?string $userId = null,
        array $clickIds = [],
        private readonly ?string $userAgent = null,
        private readonly ?string $pageLocation = null,
        private readonly ?string $pageReferrer = null
    ) {
        $accepted = [];
        foreach ($clickIds as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new \InvalidArgumentException('Every click id is a string keyed by its name.');
            }
            $accepted[$name] = $value;
        }
        $this->clickIds = $accepted;
    }

    /**
     * @inheritDoc
     */
    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    /**
     * @inheritDoc
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * @inheritDoc
     */
    public function getUserId(): ?string
    {
        return $this->userId;
    }

    /**
     * @inheritDoc
     */
    public function getClickIds(): array
    {
        return $this->clickIds;
    }

    /**
     * @inheritDoc
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * @inheritDoc
     */
    public function getPageLocation(): ?string
    {
        return $this->pageLocation;
    }

    /**
     * @inheritDoc
     */
    public function getPageReferrer(): ?string
    {
        return $this->pageReferrer;
    }

    /**
     * @inheritDoc
     */
    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
