<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\DataLayer\Model\Catalog;

/**
 * The active categories of one store view's catalog, and the rule that picks the category a product reports
 *
 * Each node is an active category below the store's root catalog: its path (the category ids from the top level
 * below the root catalog down to the category itself), its level, its position among its siblings and its name in
 * the store view. The store's root catalog and the global root are never part of a path. A category is reported only
 * when it and every category above it are in the tree, so an active category below an inactive one is not reported.
 *
 * Immutable and valid by construction; built through its generated factory.
 */
class CategoryTree
{
    /**
     * GA4 reports a category path in at most five levels (item_category … item_category5)
     */
    public const MAX_NAMES = 5;

    /**
     * @var array<int, array{path: list<int>, level: int, position: int, name: string}>
     */
    private readonly array $nodes;

    /**
     * @param array<mixed> $nodes Category id to ['path' => list<int>, 'level' => int, 'position' => int,
     *     'name' => string]; the path ends with the category's own id
     * @throws \InvalidArgumentException When a node is not of that shape
     */
    public function __construct(array $nodes = [])
    {
        $accepted = [];
        foreach ($nodes as $categoryId => $node) {
            if (!is_int($categoryId) || $categoryId <= 0) {
                throw new \InvalidArgumentException('A category tree is keyed by positive category ids.');
            }
            $accepted[$categoryId] = $this->acceptedNode($categoryId, $node);
        }
        $this->nodes = $accepted;
    }

    /**
     * Whether the category is reported: it and every category above it are active in this store's catalog
     *
     * @param int $categoryId
     * @return bool
     */
    public function contains(int $categoryId): bool
    {
        if (!isset($this->nodes[$categoryId])) {
            return false;
        }
        foreach ($this->nodes[$categoryId]['path'] as $pathId) {
            if (!isset($this->nodes[$pathId])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the ids along the category's path, top level first, ending with the category itself
     *
     * @param int $categoryId
     * @return list<int> Empty for a category that is not reported
     */
    public function pathIds(int $categoryId): array
    {
        return $this->contains($categoryId) ? $this->nodes[$categoryId]['path'] : [];
    }

    /**
     * Get the names along the category's path, top level first, at most five
     *
     * Categories without a name in the store view are left out of the list.
     *
     * @param int $categoryId
     * @return list<string> Empty for a category that is not reported
     */
    public function pathNames(int $categoryId): array
    {
        $names = [];
        foreach ($this->pathIds($categoryId) as $pathId) {
            $name = $this->nodes[$pathId]['name'];
            if ($name === '') {
                continue;
            }
            $names[] = $name;
            if (count($names) === self::MAX_NAMES) {
                break;
            }
        }

        return $names;
    }

    /**
     * Pick the category a product reports from the categories it is assigned to
     *
     * The preferred category wins when it is reported and is either assigned or lies on the path of an assigned
     * reported category (a product listed in an anchor category through one of its sub-categories). Otherwise the
     * deepest assigned reported category wins; equal depths go to the lower position, then to the lower id.
     *
     * @param array<int> $assignedCategoryIds
     * @param int|null $preferredCategoryId
     * @return int|null Null when no assigned category is reported
     */
    public function choose(array $assignedCategoryIds, ?int $preferredCategoryId = null): ?int
    {
        $reported = array_values(array_unique(array_filter(
            $assignedCategoryIds,
            fn (int $categoryId): bool => $this->contains($categoryId)
        )));
        if ($reported === []) {
            return null;
        }
        if ($preferredCategoryId !== null && $this->isOnAnyPath($preferredCategoryId, $reported)) {
            return $preferredCategoryId;
        }

        usort($reported, fn (int $left, int $right): int => $this->compareDepthFirst($left, $right));

        return $reported[0];
    }

    /**
     * Get the nodes in the shape the constructor accepts, for storage
     *
     * @return array<int, array{path: list<int>, level: int, position: int, name: string}>
     */
    public function toArray(): array
    {
        return $this->nodes;
    }

    /**
     * Whether the category is reported and lies on the path of one of the given reported categories
     *
     * @param int $categoryId
     * @param list<int> $reportedCategoryIds
     * @return bool
     */
    private function isOnAnyPath(int $categoryId, array $reportedCategoryIds): bool
    {
        if (!$this->contains($categoryId)) {
            return false;
        }
        foreach ($reportedCategoryIds as $reportedCategoryId) {
            if (in_array($categoryId, $this->nodes[$reportedCategoryId]['path'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Order two reported categories deepest first, then by position, then by id
     *
     * @param int $left
     * @param int $right
     * @return int
     */
    private function compareDepthFirst(int $left, int $right): int
    {
        return [$this->nodes[$right]['level'], $this->nodes[$left]['position'], $left]
            <=> [$this->nodes[$left]['level'], $this->nodes[$right]['position'], $right];
    }

    /**
     * Check one node's shape
     *
     * @param int $categoryId
     * @param mixed $node
     * @return array{path: list<int>, level: int, position: int, name: string}
     * @throws \InvalidArgumentException When the node is not of the accepted shape
     */
    private function acceptedNode(int $categoryId, mixed $node): array
    {
        if (!is_array($node)
            || !isset($node['path'], $node['level'], $node['position'], $node['name'])
            || !is_array($node['path'])
            || !is_int($node['level'])
            || !is_int($node['position'])
            || !is_string($node['name'])
        ) {
            throw new \InvalidArgumentException(sprintf(
                'Category %d needs a path, a level, a position and a name.',
                $categoryId
            ));
        }

        $path = [];
        foreach ($node['path'] as $pathId) {
            if (!is_int($pathId) || $pathId <= 0) {
                throw new \InvalidArgumentException(sprintf(
                    'The path of category %d holds something other than a category id.',
                    $categoryId
                ));
            }
            $path[] = $pathId;
        }
        if ($path === [] || $path[count($path) - 1] !== $categoryId) {
            throw new \InvalidArgumentException(sprintf('The path of category %d must end with it.', $categoryId));
        }

        return ['path' => $path, 'level' => $node['level'], 'position' => $node['position'], 'name' => $node['name']];
    }
}
