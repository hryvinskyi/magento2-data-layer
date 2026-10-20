<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Config;

use Hryvinskyi\DataLayer\Api\Config\DraftSettingReaderInterface;
use Hryvinskyi\DataLayer\Api\Config\DraftSettingsInterface;

/**
 * The request's settings draft: opened around one piece of work, read by every setting reader, cleared afterwards
 *
 * One shared instance serves both roles, so the readers see the draft the work was given. The draft is always
 * cleared, whether the work returns or throws, so nothing drafted can leak into the rest of the request; a draft is
 * never opened inside another, because two scopes' values in one overlay would answer for neither. With no draft
 * open, a read costs one `isset`.
 */
class DraftSettings implements DraftSettingsInterface, DraftSettingReaderInterface
{
    /**
     * A config path: at least `section/group/field`, no empty segment
     */
    private const PATH_PATTERN = '#^[^/\s]+(/[^/\s]+){2,}$#D';

    /**
     * @var array<string, string>|null Null while no draft is open
     */
    private ?array $draft = null;

    /**
     * @inheritDoc
     */
    public function withDraft(array $values, callable $work): mixed
    {
        if ($this->draft !== null) {
            throw new \LogicException('A settings draft is already open; drafts cannot be nested.');
        }
        foreach (array_keys($values) as $path) {
            if (preg_match(self::PATH_PATTERN, $path) !== 1) {
                throw new \InvalidArgumentException(
                    sprintf('"%s" cannot be drafted: it is not a section/group/field config path.', $path)
                );
            }
        }

        $this->draft = $values;
        try {
            return $work();
        } finally {
            $this->draft = null;
        }
    }

    /**
     * @inheritDoc
     */
    public function has(string $path): bool
    {
        return isset($this->draft[$path]);
    }

    /**
     * @inheritDoc
     */
    public function value(string $path): string
    {
        if (!isset($this->draft[$path])) {
            throw new \OutOfBoundsException(sprintf('The config path "%s" is not drafted.', $path));
        }

        return $this->draft[$path];
    }
}
