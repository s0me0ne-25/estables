<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Catalog;

use Estables\Core\Paths;
use Estables\Db\Connection;

/**
 * Rubrics in the database: the tree, editing, moving and deleting, plus
 * former addresses for 301 redirects.
 *
 * Every change drops the cached tree. When a rubric gets a new address (its
 * slug changes or it moves), the old addresses of it and of its whole
 * subtree go to slug_history.
 *
 * @phpstan-import-type Node from RubricTree
 *
 * @phpstan-type RubricInput array{parent: int, name: string, title: string, slug: string, description: string, visible: bool}
 */
final class RubricRepository
{
    public const HISTORY_KIND = 'rubric';
    public const MAX_NAME = 150;
    public const MAX_TITLE = 255;
    public const MAX_DESCRIPTION = 20000;

    /** Longest public path "a/b/c" that slug_history can keep. */
    private const MAX_URL_PATH = 700;

    private ?RubricTree $tree = null;

    public function __construct(
        private readonly Connection $db,
        private readonly Paths $paths,
    ) {}

    /**
     * The whole tree, from the cache when possible.
     */
    public function tree(): RubricTree
    {
        if ($this->tree !== null) {
            return $this->tree;
        }
        $file = $this->cacheFile();
        if (is_file($file)) {
            $nodes = @unserialize((string) file_get_contents($file), ['allowed_classes' => false]);
            if (\is_array($nodes)) {
                /** @var list<Node> $nodes */
                return $this->tree = new RubricTree($nodes);
            }
        }
        $this->tree = $this->loadTree(true);
        try {
            $this->paths->writeAtomic($file, serialize($this->tree->toArray()));
        } catch (\RuntimeException) {
            // Not writable: the next request reads the database again.
        }

        return $this->tree;
    }

    public function find(int $id): ?Rubric
    {
        $row = $this->db->row('SELECT * FROM rubrics WHERE id = :id', ['id' => $id]);

        return $row === null ? null : Rubric::fromRow($row);
    }

    /**
     * The rubric that used to live at "a/b/c", or null.
     */
    public function formerAddress(string $path): ?int
    {
        $id = $this->db->value(
            'SELECT target_id FROM slug_history WHERE kind = :kind AND old_path = :path',
            ['kind' => self::HISTORY_KIND, 'path' => $path],
        );

        return $id === null ? null : (int) $id;
    }

    /**
     * Checks the form; the slug may be empty and is then made from the name.
     *
     * @param RubricInput $input
     *
     * @return array<string, string> messages by field
     */
    public function validate(array $input, ?int $id = null): array
    {
        $tree = $this->loadTree();
        $errors = [];
        if ($input['name'] === '') {
            $errors['name'] = 'Укажите название.';
        } elseif (mb_strlen($input['name']) > self::MAX_NAME) {
            $errors['name'] = 'Не длиннее ' . self::MAX_NAME . ' символов.';
        }
        if (mb_strlen($input['title']) > self::MAX_TITLE) {
            $errors['title'] = 'Не длиннее ' . self::MAX_TITLE . ' символов.';
        }
        if (mb_strlen($input['description']) > self::MAX_DESCRIPTION) {
            $errors['description'] = 'Не длиннее 20 000 символов.';
        }

        $parent = $input['parent'];
        if ($parent !== 0 && !$tree->has($parent)) {
            $errors['parent'] = 'Такого раздела нет.';
        } elseif ($id !== null && ($parent === $id || \in_array($parent, $tree->descendants($id), true))) {
            $errors['parent'] = 'Раздел нельзя вложить в него самого или в его подраздел.';
        }

        if ($input['slug'] !== '') {
            if (!Slug::isValid($input['slug'])) {
                $errors['slug'] = 'Только строчные латинские буквы, цифры и дефисы между ними, до ' . Slug::MAX_LENGTH . ' символов.';
            } elseif (!isset($errors['parent']) && $this->siblingWithSlug($tree, $parent, $input['slug'], $id) !== null) {
                $errors['slug'] = 'Такой адрес уже есть у соседнего раздела.';
            }
        }
        if (!isset($errors['parent']) && !isset($errors['slug'])) {
            $prefix = $parent === 0 ? '' : $tree->slugPath($parent) . '/';
            $slug = $input['slug'] !== '' ? $input['slug'] : str_repeat('x', Slug::MAX_LENGTH);
            if (\strlen($prefix . $slug) > self::MAX_URL_PATH) {
                $errors['slug'] = 'Адрес раздела получается слишком длинным: слишком глубокая вложенность.';
            }
        }

        return $errors;
    }

    /**
     * Adds a rubric at the end of its parent's list.
     *
     * @param RubricInput $input validated
     *
     * @return int id of the new rubric
     */
    public function create(array $input): int
    {
        $id = $this->db->transaction(function () use ($input): int {
            $tree = $this->loadTree();
            $parent = $input['parent'];
            $slug = $input['slug'] !== '' ? $input['slug'] : $this->freeSlug($tree, $parent, $input['name'], null);
            $now = Connection::now();
            $this->db->run(
                'INSERT INTO rubrics (parent_id, path, slug, name, title, description, position, is_visible, created_at, updated_at)
                 VALUES (:parent, \'\', :slug, :name, :title, :description, :position, :visible, :created, :updated)',
                [
                    'parent' => $parent === 0 ? null : $parent,
                    'slug' => $slug,
                    'name' => $input['name'],
                    'title' => $input['title'],
                    'description' => $input['description'],
                    'position' => $this->nextPosition($parent),
                    'visible' => (int) $input['visible'],
                    'created' => $now,
                    'updated' => $now,
                ],
            );
            $id = (int) $this->db->pdo()->lastInsertId();
            $this->db->run('UPDATE rubrics SET path = :path WHERE id = :id', [
                'path' => $this->idPath($parent) . $id . '/',
                'id' => $id,
            ]);
            $this->forgetAddress(fn(RubricTree $t): array => [$t->slugPath($id)]);

            return $id;
        });
        // After the commit, so a parallel request cannot cache the old tree again
        $this->invalidate();

        return $id;
    }

    /**
     * Saves the form of an existing rubric. A new parent puts it at the end
     * of that parent's list.
     *
     * @param RubricInput $input validated
     */
    public function update(int $id, array $input): void
    {
        $this->db->transaction(function () use ($id, $input): void {
            $before = $this->loadTree();
            $old = $before->node($id);
            $parent = $input['parent'];
            $slug = $input['slug'] !== '' ? $input['slug'] : $this->freeSlug($before, $parent, $input['name'], $id);
            $moved = $parent !== $old['parent'];
            $this->db->run(
                'UPDATE rubrics SET parent_id = :parent, slug = :slug, name = :name, title = :title,
                    description = :description, is_visible = :visible, updated_at = :updated'
                    . ($moved ? ', position = :position' : '') . ' WHERE id = :id',
                [
                    'parent' => $parent === 0 ? null : $parent,
                    'slug' => $slug,
                    'name' => $input['name'],
                    'title' => $input['title'],
                    'description' => $input['description'],
                    'visible' => (int) $input['visible'],
                    'updated' => Connection::now(),
                    'id' => $id,
                ] + ($moved ? ['position' => $this->nextPosition($parent)] : []),
            );
            if ($moved) {
                $this->rewriteIdPaths($id, $parent);
            }
            if ($moved || $slug !== $old['slug']) {
                $this->recordAddresses($before, $id);
            }
        });
        $this->invalidate();
    }

    /**
     * Puts the rubric at $index among the children of $parent (0: top level).
     */
    public function move(int $id, int $parent, int $index): void
    {
        $this->db->transaction(function () use ($id, $parent, $index): void {
            $before = $this->loadTree();
            if (!$before->has($id) || ($parent !== 0 && !$before->has($parent))) {
                throw new RubricException('Раздел не найден: возможно, его уже удалили. Обновите страницу.');
            }
            if ($parent === $id || \in_array($parent, $before->descendants($id), true)) {
                throw new RubricException('Раздел нельзя вложить в него самого или в его подраздел.');
            }
            $node = $before->node($id);
            $moved = $parent !== $node['parent'];
            if ($moved && $this->siblingWithSlug($before, $parent, $node['slug'], $id) !== null) {
                $where = $parent === 0 ? 'на верхнем уровне' : 'в разделе «' . $before->node($parent)['name'] . '»';
                throw new RubricException("{$where} уже есть подраздел с адресом «{$node['slug']}». Сначала измените адрес одного из них.");
            }
            if ($moved) {
                $this->db->run('UPDATE rubrics SET parent_id = :parent, updated_at = :updated WHERE id = :id', [
                    'parent' => $parent === 0 ? null : $parent,
                    'updated' => Connection::now(),
                    'id' => $id,
                ]);
                $this->rewriteIdPaths($id, $parent);
            }
            $siblings = array_values(array_filter($before->children($parent), static fn(int $s): bool => $s !== $id));
            array_splice($siblings, max(0, min($index, \count($siblings))), 0, [$id]);
            foreach ($siblings as $position => $sibling) {
                $this->db->run('UPDATE rubrics SET position = :position WHERE id = :id', ['position' => $position, 'id' => $sibling]);
            }
            if ($moved) {
                $this->recordAddresses($before, $id);
            }
        });
        $this->invalidate();
    }

    /**
     * Deletes a rubric without subrubrics.
     */
    public function delete(int $id): void
    {
        $this->db->transaction(function () use ($id): void {
            $count = (int) $this->db->value('SELECT COUNT(*) FROM rubrics WHERE parent_id = :id', ['id' => $id]);
            if ($count > 0) {
                throw new RubricException('Сначала перенесите или удалите подразделы этого раздела.');
            }
            $this->db->run('DELETE FROM slug_history WHERE kind = :kind AND target_id = :id', ['kind' => self::HISTORY_KIND, 'id' => $id]);
            $this->db->run('DELETE FROM rubrics WHERE id = :id', ['id' => $id]);
        });
        $this->invalidate();
    }

    /**
     * Drops the cached tree; the next request reads the database.
     */
    public function invalidate(): void
    {
        $this->tree = null;
        @unlink($this->cacheFile());
    }

    /**
     * Slug for a new address: from the name, with "-2", "-3"... when a
     * sibling already has it.
     */
    private function freeSlug(RubricTree $tree, int $parent, string $name, ?int $id): string
    {
        $base = Slug::fromText($name);
        if ($base === '') {
            $base = 'razdel';
        }
        $slug = $base;
        for ($n = 2; $this->siblingWithSlug($tree, $parent, $slug, $id) !== null; $n++) {
            $suffix = '-' . $n;
            $slug = rtrim(substr($base, 0, Slug::MAX_LENGTH - \strlen($suffix)), '-') . $suffix;
        }

        return $slug;
    }

    private function siblingWithSlug(RubricTree $tree, int $parent, string $slug, ?int $except): ?int
    {
        foreach ($tree->children($parent) as $child) {
            if ($child !== $except && $tree->node($child)['slug'] === $slug) {
                return $child;
            }
        }

        return null;
    }

    private function nextPosition(int $parent): int
    {
        $max = $parent === 0
            ? $this->db->value('SELECT MAX(position) FROM rubrics WHERE parent_id IS NULL')
            : $this->db->value('SELECT MAX(position) FROM rubrics WHERE parent_id = :parent', ['parent' => $parent]);

        return $max === null ? 0 : (int) $max + 1;
    }

    /**
     * Materialized path of ids, "/1/4/" for rubric 4 under 1; "/" for the top level.
     */
    private function idPath(int $id): string
    {
        return $id === 0 ? '/' : (string) $this->db->value('SELECT path FROM rubrics WHERE id = :id', ['id' => $id]);
    }

    /**
     * After a move: the id paths of the rubric and its subtree start with the new parent's.
     */
    private function rewriteIdPaths(int $id, int $parent): void
    {
        $old = $this->idPath($id);
        $new = $this->idPath($parent) . $id . '/';
        $this->db->run(
            'UPDATE rubrics SET path = CONCAT(:new, SUBSTRING(path, :cut)) WHERE path LIKE :prefix',
            ['new' => $new, 'cut' => \strlen($old) + 1, 'prefix' => $old . '%'],
        );
    }

    /**
     * Keeps the former addresses of the rubric and its subtree for 301 redirects.
     */
    private function recordAddresses(RubricTree $before, int $id): void
    {
        $after = $this->loadTree();
        $now = Connection::now();
        foreach ([$id, ...$before->descendants($id)] as $node) {
            $old = $before->slugPath($node);
            if ($old === $after->slugPath($node)) {
                continue;
            }
            $this->db->run(
                'INSERT INTO slug_history (kind, old_path, target_id, created_at) VALUES (:kind, :path, :target, :created)
                 ON DUPLICATE KEY UPDATE target_id = VALUES(target_id), created_at = VALUES(created_at)',
                ['kind' => self::HISTORY_KIND, 'path' => $old, 'target' => $node, 'created' => $now],
            );
        }
        $this->forgetAddress(fn(RubricTree $t): array => array_map($t->slugPath(...), [$id, ...$t->descendants($id)]));
    }

    /**
     * A live address never redirects: drops history entries that a rubric
     * occupies now.
     *
     * @param callable(RubricTree): list<string> $paths
     */
    private function forgetAddress(callable $paths): void
    {
        foreach ($paths($this->loadTree()) as $path) {
            $this->db->run('DELETE FROM slug_history WHERE kind = :kind AND old_path = :path', ['kind' => self::HISTORY_KIND, 'path' => $path]);
        }
    }

    /**
     * Reads the tree; with $stats also the aggregates of published items.
     */
    private function loadTree(bool $stats = false): RubricTree
    {
        $nodes = [];
        foreach ($this->db->rows('SELECT id, parent_id, slug, name, is_visible FROM rubrics ORDER BY position, id') as $row) {
            $id = (int) $row['id'];
            $nodes[$id] = [
                'id' => $id,
                'parent' => (int) ($row['parent_id'] ?? 0),
                'slug' => (string) $row['slug'],
                'name' => (string) $row['name'],
                'visible' => (bool) $row['is_visible'],
                'count' => 0,
                'variants' => 0,
                'yearMin' => null,
                'yearMax' => null,
            ];
        }
        if ($stats && $nodes !== []) {
            $this->aggregate($nodes);
        }

        return new RubricTree(array_values($nodes));
    }

    /**
     * Fills count, variants and the span of years of every node from the
     * published items of its subtree; each item counts once per node.
     *
     * @param array<int, Node> $nodes
     */
    private function aggregate(array &$nodes): void
    {
        $items = [];
        $members = [];
        $links = $this->db->rows(
            'SELECT ir.item_id, ir.rubric_id, i.year_from, i.year_to,
                (SELECT COUNT(*) FROM item_variants v WHERE v.item_id = i.id) AS variants
             FROM item_rubrics ir JOIN items i ON i.id = ir.item_id WHERE i.is_published = 1',
        );
        foreach ($links as $link) {
            $item = (int) $link['item_id'];
            $from = $link['year_from'] === null ? null : (int) $link['year_from'];
            $to = $link['year_to'] === null ? null : (int) $link['year_to'];
            $items[$item] = ['variants' => (int) $link['variants'], 'min' => $from ?? $to, 'max' => $to ?? $from];
            // Up the tree; a hidden rubric keeps its items to itself
            for ($rubric = (int) $link['rubric_id']; isset($nodes[$rubric]); $rubric = $nodes[$rubric]['parent']) {
                $members[$rubric][$item] = true;
                if (!$nodes[$rubric]['visible']) {
                    break;
                }
            }
        }
        foreach ($members as $rubric => $set) {
            $node = &$nodes[$rubric];
            $node['count'] = \count($set);
            foreach (array_keys($set) as $item) {
                $node['variants'] += $items[$item]['variants'];
                $min = $items[$item]['min'];
                $max = $items[$item]['max'];
                if ($min !== null && ($node['yearMin'] === null || $min < $node['yearMin'])) {
                    $node['yearMin'] = $min;
                }
                if ($max !== null && ($node['yearMax'] === null || $max > $node['yearMax'])) {
                    $node['yearMax'] = $max;
                }
            }
            unset($node);
        }
    }

    private function cacheFile(): string
    {
        return $this->paths->cache() . '/rubrics.ser';
    }
}
