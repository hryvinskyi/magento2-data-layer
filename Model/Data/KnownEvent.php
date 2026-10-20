<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Data;

use Hryvinskyi\DataLayer\Api\Data\KnownEventInterface;

/**
 * A known event's description, valid by construction
 *
 * A name that is not a GA4 event name, an empty label and a group or source code that is neither empty nor lower-case
 * letters and underscores are refused when the description is created. The texts are kept as registered, in the
 * source language, and translated each time they are read, into the locale of the request reading them; the codes are
 * never translated.
 */
class KnownEvent implements KnownEventInterface
{
    /**
     * A group or source code: lower-case letters and underscores
     */
    public const CODE_PATTERN = '/^[a-z_]+$/D';

    /**
     * @param string $name
     * @param string $label
     * @param string $group
     * @param string $trigger
     * @param string $source
     * @param string $hint
     * @param bool $browserOriginated Whether the shopper's browser detects the event
     * @param string $groupCode The group's stable code, or empty for none
     * @param string $sourceCode The source's stable code, or empty for none
     * @throws \InvalidArgumentException When the name is not a GA4 event name, the label is empty or a code is not a
     *     code
     */
    public function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly string $group = '',
        private readonly string $trigger = '',
        private readonly string $source = '',
        private readonly string $hint = '',
        private readonly bool $browserOriginated = false,
        private readonly string $groupCode = '',
        private readonly string $sourceCode = ''
    ) {
        if (preg_match(Ga4Name::PATTERN, $name) !== 1) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a GA4 event name.', $name));
        }
        if (trim($label) === '') {
            throw new \InvalidArgumentException(sprintf('The known event "%s" needs a label.', $name));
        }
        foreach ([$groupCode, $sourceCode] as $code) {
            if ($code !== '' && preg_match(self::CODE_PATTERN, $code) !== 1) {
                throw new \InvalidArgumentException(sprintf(
                    'The known event "%s" has the code "%s"; use lower-case letters and underscores.',
                    $name,
                    $code
                ));
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return $this->translated($this->label);
    }

    /**
     * @inheritDoc
     */
    public function getGroup(): string
    {
        return $this->translated($this->group);
    }

    /**
     * @inheritDoc
     */
    public function getGroupCode(): string
    {
        return $this->groupCode;
    }

    /**
     * @inheritDoc
     */
    public function getTrigger(): string
    {
        return $this->translated($this->trigger);
    }

    /**
     * @inheritDoc
     */
    public function getSource(): string
    {
        return $this->translated($this->source);
    }

    /**
     * @inheritDoc
     */
    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    /**
     * @inheritDoc
     */
    public function getHint(): string
    {
        return $this->translated($this->hint);
    }

    /**
     * @inheritDoc
     */
    public function isBrowserOriginated(): bool
    {
        return $this->browserOriginated;
    }

    /**
     * Translate a registered text into the current locale; an empty text stays empty
     *
     * @param string $text
     * @return string
     */
    private function translated(string $text): string
    {
        return $text === '' ? '' : (string) __($text);
    }
}
