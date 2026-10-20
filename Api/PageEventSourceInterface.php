<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Api;

use Hryvinskyi\DataLayerApi\Api\Data\EventInterface;

/**
 * Supplies the events that describe the page being rendered (`view_item`, `view_cart`, `begin_checkout`,
 * `purchase`)
 *
 * A source is the part of a page that knows its subject (the product shown, the cart, the order placed); the page's
 * events are collected from its sources while it renders. The page may be served from the full-page cache, so a source
 * prints every enabled event whatever its channel and leaves delivery to the browser. A source never throws: a failure
 * is logged with its context and the source supplies nothing, so the page always renders.
 */
interface PageEventSourceInterface
{
    /**
     * Get the page's events
     *
     * @return list<EventInterface> Empty when the page has nothing to report or the events could not be built
     */
    public function events(): array;
}
