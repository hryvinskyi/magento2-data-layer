<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Data;

/**
 * A named selection of events a store can switch on in one step
 *
 * The texts are already translated. The events are known events only, in the order the known events list them.
 *
 * @api
 */
interface EventPresetInterface
{
    /**
     * Get the preset's stable key
     *
     * @return string Lower-case letters, digits and underscores
     */
    public function getKey(): string;

    /**
     * Get the admin label
     *
     * @return string Never empty; translated into the current locale
     */
    public function getLabel(): string;

    /**
     * Get the admin description
     *
     * @return string Translated into the current locale; empty when the registering module gave none
     */
    public function getDescription(): string;

    /**
     * Get the position among presets; lower comes first
     *
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * Get the name of the glyph the admin draws for the preset
     *
     * A presentation hint only: an admin that does not know the glyph draws a generic one.
     *
     * @return string|null Null when the registering module named none
     */
    public function getIcon(): ?string;

    /**
     * Get the names of the events the preset switches on
     *
     * @return list<string> Never empty
     */
    public function getEvents(): array;
}
