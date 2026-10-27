<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Catalog;

/**
 * The whole rubricator in memory: read in one query, cached in var/cache/.
 * Rubrics number in the hundreds, so walking arrays is cheaper than asking
 * the database for every ancestor or child.
 *
 * Parent 0 stands for the top level.
 *
 * Every node carries aggregates of its subtree: distinct published items
 * ("count"), their variants and the span of their years. Items of a hidden
 * rubric count in that rubric but not above it.
 *
 * Its cover (an image packed by Image::PACKED) is its own cover, else the
 * first photo of its first published item in the authorial order, else the
 * cover of its first visible subrubric that has one.
 *
 * @phpstan-type Node array{id: int, parent: int, slug: string, name: string, visible: bool, count: int, variants: int, yearMin: ?int, yearMax: ?int, cover: ?string}
 */
final class RubricTree
{
    /** @var array<int, Node> */
    private array $nodes = [];

    /** @var array<int, list<int>> child ids by parent id, in order */
    private array $children = [];

    /**
     * @param list<Node> $nodes ordered by position within each parent
     */
    public function __construct(array $nodes)
    {
        foreach ($nodes as $node) {
            $this->nodes[$node['id']] = $node;
            $this->children[$node['parent']][] = $node['id'];
        }
    }

    /**
     * @return list<Node>
     */
    public function toArray(): array
    {
        return array_values($this->nodes);
    }

    public function isEmpty(): bool
    {
        return $this->nodes === [];
    }

    public function has(int $id): bool
    {
        return isset($this->nodes[$id]);
    }

    /**
     * @return Node
     */
    public function node(int $id): array
    {
        return $this->nodes[$id] ?? throw new \OutOfBoundsException("No rubric {$id}");
    }

    /**
     * @return list<int>
     */
    public function children(int $parentId): array
    {
        return $this->children[$parentId] ?? [];
    }

    /**
     * Ids from the top level down to the parent of $id.
     *
     * @return list<int>
     */
    public function ancestors(int $id): array
    {
        $result = [];
        for ($parent = $this->node($id)['parent']; $parent !== 0; $parent = $this->node($parent)['parent']) {
            array_unshift($result, $parent);
        }

        return $result;
    }

    /**
     * Every id below $id, depth first.
     *
     * @return list<int>
     */
    public function descendants(int $id): array
    {
        $result = [];
        foreach ($this->children($id) as $child) {
            $result[] = $child;
            array_push($result, ...$this->descendants($child));
        }

        return $result;
    }

    public function depth(int $id): int
    {
        return \count($this->ancestors($id));
    }

    /**
     * Slugs joined by "/", e.g. "figurki/farfor/lfz".
     */
    public function slugPath(int $id): string
    {
        $slugs = array_map(fn(int $a): string => $this->node($a)['slug'], [...$this->ancestors($id), $id]);

        return implode('/', $slugs);
    }

    /**
     * Public address of the rubric page.
     */
    public function url(int $id): string
    {
        return '/catalog/' . $this->slugPath($id) . '/';
    }

    /**
     * The rubric at "a/b/c", or null.
     */
    public function findByPath(string $path): ?int
    {
        $parent = 0;
        foreach (explode('/', $path) as $slug) {
            $found = null;
            foreach ($this->children($parent) as $child) {
                if ($this->nodes[$child]['slug'] === $slug) {
                    $found = $child;
                    break;
                }
            }
            if ($found === null) {
                return null;
            }
            $parent = $found;
        }

        return $parent === 0 ? null : $parent;
    }

    /**
     * Whether guests see the rubric: it and every ancestor are visible.
     */
    public function isShown(int $id): bool
    {
        foreach ([...$this->ancestors($id), $id] as $node) {
            if (!$this->nodes[$node]['visible']) {
                return false;
            }
        }

        return true;
    }

    /**
     * The tree in display order with nesting levels, for selects.
     *
     * @param int|null $exclude a rubric left out together with its subtree
     *
     * @return list<array{id: int, depth: int}>
     */
    public function flatten(?int $exclude = null, int $parent = 0, int $depth = 0): array
    {
        $result = [];
        foreach ($this->children($parent) as $id) {
            if ($id === $exclude) {
                continue;
            }
            $result[] = ['id' => $id, 'depth' => $depth];
            array_push($result, ...$this->flatten($exclude, $id, $depth + 1));
        }

        return $result;
    }
}
