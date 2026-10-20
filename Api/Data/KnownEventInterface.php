<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api\Data;

/**
 * How a known event is described to the people who choose which events are sent
 *
 * The texts are already translated. The group puts related events together (browsing, cart, checkout…), the trigger
 * says what the shopper or the store does to cause the event, and the source says where the module detects it (a
 * page, a list, a click, the server, an order). A module that registers only a label leaves the other texts empty.
 * The group and the source also come as stable codes (lower-case letters and underscores), which do not change with
 * the admin's language, for anything that must pick by group or source rather than show it.
 *
 * An event is browser-originated when the shopper's browser detects it (a page viewed, a list seen, a click on a
 * product or a banner): only such an event may reach the server by the browser posting it. An event the server detects
 * (a cart line saved, a checkout step, an account action) or an order-level event (a purchase, a refund) is not, so a
 * browser can never post one on the store's behalf.
 */
interface KnownEventInterface
{
    /**
     * Get the GA4 event name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the admin label
     *
     * @return string Never empty; translated into the current locale
     */
    public function getLabel(): string;

    /**
     * Get the group the event is listed in
     *
     * @return string Translated into the current locale; empty when the registering module named none
     */
    public function getGroup(): string;

    /**
     * Get the stable code of the group the event is listed in
     *
     * @return string Lower-case letters and underscores; empty when the registering module named none
     */
    public function getGroupCode(): string;

    /**
     * Get what causes the event
     *
     * @return string Translated into the current locale; empty when the registering module named nothing
     */
    public function getTrigger(): string;

    /**
     * Get where the event is detected
     *
     * @return string Translated into the current locale; empty when the registering module named nothing
     */
    public function getSource(): string;

    /**
     * Get the stable code of where the event is detected
     *
     * @return string Lower-case letters and underscores; empty when the registering module named none
     */
    public function getSourceCode(): string;

    /**
     * Get a short note on how the event behaves on each channel
     *
     * @return string Translated into the current locale; empty when there is none
     */
    public function getHint(): string;

    /**
     * Whether the shopper's browser detects the event
     *
     * @return bool False when the registering module did not say so
     */
    public function isBrowserOriginated(): bool;
}
