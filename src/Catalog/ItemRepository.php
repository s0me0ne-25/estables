<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Catalog;

use Estables\Db\Connection;

/**
 * Items in the database: editing, rubric links with the authorial order,
 * characteristics, variants and the lists of the admin area.
 *
 * Authorial order: item_rubrics.position, separately in every rubric, with
 * gaps of POSITION_STEP. A move writes one value between the neighbours;
 * when no gap is left, the rubric is renumbered. A new link goes to the top.
 *
 * Every change that can affect counts or the public tree drops the cached
 * rubric tree.
 *
 * @phpstan-type ItemInput array{
 *     catno: string, name: string, summary: string, description: string,
 *     yearFrom: string, yearTo: string, published: bool,
 *     rubrics: list<int>, main: int,
 *     attributes: list<array{name: string, value: string}>,
 *     variants: list<array{number: string, finish: string, years: string}>
 * }
 * @phpstan-type ItemRow array{id: int, catno: string, slug: string, name: string, summary: string,
 *     yearFrom: ?int, yearTo: ?int, published: bool, updatedAt: \DateTimeImmutable, variants: int, main: int, rubrics: list<int>}
 */
final class ItemRepository
{
    public const POSITION_STEP = 1024;
    public const HISTORY_KIND = 'item';
    public const MAX_CATNO = 64;
    public const MAX_NAME = 255;
    public const MIN_YEAR = 1000;
    public const MAX_YEAR = 2100;

    /** Rows per UPDATE when a whole rubric is renumbered. */
    private const RENUMBER_CHUNK = 500;

    /** Columns of the lists. */
    private const ROW_COLUMNS = 'i.id, i.catno, i.slug, i.name, i.summary, i.year_from, i.year_to, i.is_published, i.updated_at,
        (SELECT COUNT(*) FROM item_variants v WHERE v.item_id = i.id) AS variant_count,
        (SELECT GROUP_CONCAT(m.rubric_id ORDER BY m.is_main DESC, m.added_at, m.rubric_id) FROM item_rubrics m WHERE m.item_id = i.id) AS rubric_ids';

    public function __construct(
        private readonly Connection $db,
        private readonly RubricRepository $rubrics,
    ) {}

    public function find(int $id): ?Item
    {
        $row = $this->db->row('SELECT * FROM items WHERE id = :id', ['id' => $id]);

        return $row === null ? null : Item::fromRow($row);
    }

    public function findBySlug(string $slug): ?Item
    {
        $row = $this->db->row('SELECT * FROM items WHERE slug = :slug', ['slug' => $slug]);

        return $row === null ? null : Item::fromRow($row);
    }

    /**
     * The item that used to have this address, or null.
     */
    public function formerAddress(string $slug): ?int
    {
        $id = $this->db->value(
            'SELECT target_id FROM slug_history WHERE kind = :kind AND old_path = :path',
            ['kind' => self::HISTORY_KIND, 'path' => $slug],
        );

        return $id === null ? null : (int) $id;
    }

    /**
     * Rubrics of an item, the main one first, then by the time they were added.
     *
     * @return list<int>
     */
    public function rubricsOf(int $itemId): array
    {
        return $this->db->ints(
            'SELECT rubric_id FROM item_rubrics WHERE item_id = :id ORDER BY is_main DESC, added_at, rubric_id',
            ['id' => $itemId],
        );
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    public function attributes(int $itemId): array
    {
        $rows = $this->db->rows(
            'SELECT n.name, a.value FROM item_attributes a JOIN attribute_names n ON n.id = a.name_id
             WHERE a.item_id = :id ORDER BY a.position, a.id',
            ['id' => $itemId],
        );

        return array_map(static fn(array $r): array => ['name' => (string) $r['name'], 'value' => (string) $r['value']], $rows);
    }

    /**
     * @return list<array{number: string, finish: string, years: string}>
     */
    public function variants(int $itemId): array
    {
        $rows = $this->db->rows(
            'SELECT number, finish, years FROM item_variants WHERE item_id = :id ORDER BY position, id',
            ['id' => $itemId],
        );

        return array_map(static fn(array $r): array => [
            'number' => (string) $r['number'],
            'finish' => (string) $r['finish'],
            'years' => (string) $r['years'],
        ], $rows);
    }

    /**
     * Names of characteristics for suggestions, the most used first.
     *
     * @return list<string>
     */
    public function attributeNames(): array
    {
        return array_values(array_map('strval', $this->db->run(
            'SELECT n.name FROM attribute_names n LEFT JOIN item_attributes a ON a.name_id = n.id
             GROUP BY n.id, n.name ORDER BY COUNT(a.id) DESC, n.name',
        )->fetchAll(\PDO::FETCH_COLUMN)));
    }

    /**
     * @return array{total: int, drafts: int}
     */
    public function totals(): array
    {
        $row = $this->db->row('SELECT COUNT(*) AS total, COALESCE(SUM(is_published = 0), 0) AS drafts FROM items');

        return ['total' => (int) ($row['total'] ?? 0), 'drafts' => (int) ($row['drafts'] ?? 0)];
    }

    /**
     * Items linked to each rubric directly, and how many of them have other rubrics too.
     *
     * @return array<int, array{items: int, shared: int}>
     */
    public function rubricStats(): array
    {
        $result = [];
        $rows = $this->db->rows(
            'SELECT ir.rubric_id, COUNT(*) AS items,
                SUM(EXISTS (SELECT 1 FROM item_rubrics o WHERE o.item_id = ir.item_id AND o.rubric_id <> ir.rubric_id)) AS shared
             FROM item_rubrics ir GROUP BY ir.rubric_id',
        );
        foreach ($rows as $row) {
            $result[(int) $row['rubric_id']] = ['items' => (int) $row['items'], 'shared' => (int) $row['shared']];
        }

        return $result;
    }

    /**
     * Checks the editor form.
     *
     * @param ItemInput $input
     *
     * @return array<string, string> messages by field
     */
    public function validate(array $input, ?int $id = null): array
    {
        $errors = [];
        if ($input['catno'] !== '') {
            if (mb_strlen($input['catno']) > self::MAX_CATNO) {
                $errors['catno'] = 'Не длиннее ' . self::MAX_CATNO . ' символов.';
            } elseif ($this->catnoTaken($input['catno'], $id)) {
                $errors['catno'] = 'Такой номер уже есть у другого предмета.';
            }
        }
        if ($input['name'] === '') {
            $errors['name'] = 'Укажите название.';
        } elseif (mb_strlen($input['name']) > self::MAX_NAME) {
            $errors['name'] = 'Не длиннее ' . self::MAX_NAME . ' символов.';
        }
        if ($input['rubrics'] === []) {
            $errors['rubrics'] = 'Добавьте хотя бы один раздел.';
        } else {
            $tree = $this->rubrics->tree();
            foreach ($input['rubrics'] as $rubric) {
                if (!$tree->has($rubric)) {
                    $errors['rubrics'] = 'Одного из разделов уже нет: возможно, его удалили. Проверьте список.';
                }
            }
        }
        if (mb_strlen($input['summary']) > 2000) {
            $errors['summary'] = 'Не длиннее 2000 символов.';
        }
        if (mb_strlen($input['description']) > 100000) {
            $errors['description'] = 'Не длиннее 100 000 символов.';
        }
        foreach (['yearFrom', 'yearTo'] as $field) {
            $year = $input[$field];
            if ($year !== '' && (!ctype_digit($year) || (int) $year < self::MIN_YEAR || (int) $year > self::MAX_YEAR)) {
                $errors[$field] = 'Год четырьмя цифрами.';
            }
        }
        if (!isset($errors['yearFrom'], $errors['yearTo']) && $input['yearFrom'] !== '' && $input['yearTo'] !== ''
            && (int) $input['yearFrom'] > (int) $input['yearTo']) {
            $errors['yearTo'] = 'Не раньше года начала.';
        }
        foreach ($input['attributes'] as $i => $attribute) {
            if ($attribute['name'] === '') {
                $errors["attributes.{$i}"] = 'Укажите характеристику или очистите строку.';
            } elseif (mb_strlen($attribute['name']) > 150 || mb_strlen($attribute['value']) > 500) {
                $errors["attributes.{$i}"] = 'Название до 150 символов, значение до 500.';
            }
        }
        foreach ($input['variants'] as $i => $variant) {
            if (mb_strlen($variant['number']) > 64 || mb_strlen($variant['finish']) > 500 || mb_strlen($variant['years']) > 64) {
                $errors["variants.{$i}"] = 'Номер до 64 символов, исполнение до 500, годы до 64.';
            }
        }

        return $errors;
    }

    /**
     * Creates or updates an item from a validated form.
     *
     * @param ItemInput $input
     *
     * @return int id of the item
     */
    public function save(?int $id, array $input, ?int $userId): int
    {
        $id = $this->db->transaction(function () use ($id, $input, $userId): int {
            $now = Connection::now();
            $fields = [
                'name' => $input['name'],
                'summary' => $input['summary'],
                'description' => $input['description'],
                'year_from' => $input['yearFrom'] === '' ? null : (int) $input['yearFrom'],
                'year_to' => $input['yearTo'] === '' ? null : (int) $input['yearTo'],
                'published' => (int) $input['published'],
                'search' => self::searchText($input),
                'updated' => $now,
                'user' => $userId,
            ];
            if ($id === null) {
                $placeholder = 'new-' . bin2hex(random_bytes(8));
                $this->db->run(
                    'INSERT INTO items (catno, catno_sort, slug, name, summary, description, year_from, year_to, is_published,
                        search_text, created_at, updated_at, updated_by)
                     VALUES (:catno, \'\', :slug, :name, :summary, :description, :year_from, :year_to, :published,
                        :search, :created, :updated, :user)',
                    ['catno' => $placeholder, 'slug' => $placeholder, 'created' => $now] + $fields,
                );
                $id = (int) $this->db->pdo()->lastInsertId();
                $oldSlug = null;
            } else {
                $this->db->run(
                    'UPDATE items SET name = :name, summary = :summary, description = :description, year_from = :year_from,
                        year_to = :year_to, is_published = :published, search_text = :search, updated_at = :updated,
                        updated_by = :user WHERE id = :id',
                    ['id' => $id] + $fields,
                );
                $oldSlug = (string) $this->db->value('SELECT slug FROM items WHERE id = :id', ['id' => $id]);
            }
            $this->assignNumber($id, $input['catno'], $oldSlug);
            $this->saveRubrics($id, $input['rubrics'], $input['main']);
            $this->saveAttributes($id, $input['attributes']);
            $this->saveVariants($id, $input['variants']);

            return $id;
        });
        $this->rubrics->invalidate();

        return $id;
    }

    /**
     * @param list<int> $ids
     */
    public function delete(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }
        $count = $this->db->transaction(function () use ($ids): int {
            [$in, $params] = self::in($ids);
            $this->db->run("DELETE FROM slug_history WHERE kind = 'item' AND target_id IN ({$in})", $params);

            return $this->db->run("DELETE FROM items WHERE id IN ({$in})", $params)->rowCount();
        });
        $this->rubrics->invalidate();

        return $count;
    }

    /**
     * @param list<int> $ids
     */
    public function setPublished(array $ids, bool $published, ?int $userId): int
    {
        if ($ids === []) {
            return 0;
        }
        [$in, $params] = self::in($ids);
        $count = $this->db->run(
            "UPDATE items SET is_published = :published, updated_at = :updated, updated_by = :user WHERE id IN ({$in})",
            ['published' => (int) $published, 'updated' => Connection::now(), 'user' => $userId] + $params,
        )->rowCount();
        $this->rubrics->invalidate();

        return $count;
    }

    /**
     * Adds items to a rubric; they go to its top in the given order.
     *
     * @param list<int> $ids
     *
     * @return int links added (items already in the rubric are skipped)
     */
    public function addToRubric(array $ids, int $rubric): int
    {
        $ids = array_values(array_unique($ids));
        $added = $this->db->transaction(function () use ($ids, $rubric): int {
            $existing = $this->itemsIn($rubric, $ids);
            $new = array_values(array_diff($this->existingItems($ids), $existing));
            $top = $this->edgePosition($rubric, 'MIN');
            $time = microtime(true);
            foreach (array_reverse($new) as $n => $item) {
                $top -= self::POSITION_STEP;
                $this->db->run(
                    'INSERT INTO item_rubrics (item_id, rubric_id, is_main, position, added_at) VALUES (:item, :rubric, 0, :position, :added)',
                    ['item' => $item, 'rubric' => $rubric, 'position' => $top, 'added' => self::microDate($time + $n * 1e-6)],
                );
            }

            return \count($new);
        });
        $this->rubrics->invalidate();

        return $added;
    }

    /**
     * Takes items out of a rubric. An item keeps its last rubric; a removed
     * main rubric passes to the next one by the time it was added.
     *
     * @param list<int> $ids
     *
     * @return array{removed: int, kept: int}
     */
    public function removeFromRubric(array $ids, int $rubric): array
    {
        $result = $this->db->transaction(function () use ($ids, $rubric): array {
            $removed = 0;
            $kept = 0;
            foreach ($this->itemsIn($rubric, $ids) as $item) {
                if ((int) $this->db->value('SELECT COUNT(*) FROM item_rubrics WHERE item_id = :item', ['item' => $item]) < 2) {
                    $kept++;
                    continue;
                }
                $this->unlink($item, $rubric);
                $removed++;
            }

            return ['removed' => $removed, 'kept' => $kept];
        });
        $this->rubrics->invalidate();

        return $result;
    }

    /**
     * Before a rubric is deleted: its links go; items for which it is the only
     * rubric move to $target (to its top) or are deleted when $target is null.
     *
     * @return array{moved: int, deleted: int, unlinked: int}
     */
    public function release(int $rubric, ?int $target): array
    {
        return $this->db->transaction(function () use ($rubric, $target): array {
            $items = $this->db->ints(
                'SELECT item_id FROM item_rubrics WHERE rubric_id = :rubric ORDER BY position, item_id',
                ['rubric' => $rubric],
            );
            $only = [];
            $unlinked = 0;
            foreach ($items as $item) {
                if ((int) $this->db->value('SELECT COUNT(*) FROM item_rubrics WHERE item_id = :item', ['item' => $item]) < 2) {
                    $only[] = $item;
                } else {
                    $this->unlink($item, $rubric);
                    $unlinked++;
                }
            }
            if ($only === []) {
                return ['moved' => 0, 'deleted' => 0, 'unlinked' => $unlinked];
            }
            if ($target === null) {
                [$in, $params] = self::in($only);
                $this->db->run("DELETE FROM slug_history WHERE kind = 'item' AND target_id IN ({$in})", $params);
                $this->db->run("DELETE FROM items WHERE id IN ({$in})", $params);

                return ['moved' => 0, 'deleted' => \count($only), 'unlinked' => $unlinked];
            }
            $already = $this->itemsIn($target, $only);
            $top = $this->edgePosition($target, 'MIN');
            foreach (array_reverse($only) as $item) {
                if (\in_array($item, $already, true)) {
                    $this->unlink($item, $rubric);
                    continue;
                }
                $top -= self::POSITION_STEP;
                // The link changes its rubric and stays the main one
                $this->db->run(
                    'UPDATE item_rubrics SET rubric_id = :target, position = :position WHERE item_id = :item AND rubric_id = :rubric',
                    ['target' => $target, 'position' => $top, 'item' => $item, 'rubric' => $rubric],
                );
            }

            return ['moved' => \count($only), 'deleted' => 0, 'unlinked' => $unlinked];
        });
    }

    /**
     * Drag and drop in the authorial order: puts the item right after
     * $after, or right before $before when $after is null.
     */
    public function moveInRubric(int $rubric, int $item, ?int $after, ?int $before): void
    {
        $this->db->transaction(function () use ($rubric, $item, $after, $before): void {
            if ($this->position($rubric, $item) === null) {
                throw new RubricException('Предмета уже нет в этом разделе. Обновите страницу.');
            }
            for ($attempt = 0; $attempt < 2; $attempt++) {
                [$low, $high] = $this->neighbourPositions($rubric, $item, $after, $before);
                $position = match (true) {
                    $low === null && $high === null => 0,
                    $low === null => $high - self::POSITION_STEP,
                    $high === null => $low + self::POSITION_STEP,
                    default => intdiv($low + $high, 2),
                };
                if (($low === null || $position > $low) && ($high === null || $position < $high)) {
                    $this->setPosition($rubric, $item, $position);

                    return;
                }
                $this->renumber($rubric, $this->order($rubric));
            }

            throw new \LogicException('No room in the authorial order after renumbering');
        });
    }

    /**
     * Moves items to the top or the bottom of the authorial order, keeping
     * their mutual order.
     *
     * @param list<int> $ids
     */
    public function toEdge(int $rubric, array $ids, bool $top): void
    {
        $this->db->transaction(function () use ($rubric, $ids, $top): void {
            $selected = $this->inOrder($rubric, $ids);
            $rest = array_values(array_diff($this->order($rubric), $selected));
            $this->renumber($rubric, $top ? [...$selected, ...$rest] : [...$rest, ...$selected]);
        });
    }

    /**
     * Puts items in a row starting at $position (1-based), keeping their mutual order.
     *
     * @param list<int> $ids
     */
    public function toPosition(int $rubric, array $ids, int $position): void
    {
        $this->db->transaction(function () use ($rubric, $ids, $position): void {
            $selected = $this->inOrder($rubric, $ids);
            $rest = array_values(array_diff($this->order($rubric), $selected));
            $index = max(0, min($position - 1, \count($rest)));
            array_splice($rest, $index, 0, $selected);
            $this->renumber($rubric, $rest);
        });
    }

    /**
     * Admin list with filters.
     *
     * @param array{q: string, rubric: int, status: string, sort: string} $filter
     *
     * @return array{rows: list<ItemRow>, total: int}
     */
    public function adminList(array $filter, int $page, int $perPage): array
    {
        $where = [];
        $params = [];
        $join = '';
        if ($filter['rubric'] > 0) {
            $join = 'JOIN item_rubrics r ON r.item_id = i.id AND r.rubric_id = :rubric';
            $params['rubric'] = $filter['rubric'];
        }
        if ($filter['q'] !== '') {
            $where[] = '(i.catno LIKE :q1 OR i.name LIKE :q2)';
            $like = '%' . addcslashes($filter['q'], '%_\\') . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
        }
        if ($filter['status'] === 'published' || $filter['status'] === 'draft') {
            $where[] = 'i.is_published = ' . ($filter['status'] === 'published' ? 1 : 0);
        }
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $order = match ($filter['sort']) {
            'author' => $filter['rubric'] > 0 ? 'r.position, i.id' : 'i.updated_at DESC, i.id DESC',
            'number' => 'i.catno_sort, i.id',
            'title' => 'i.name, i.id',
            default => 'i.updated_at DESC, i.id DESC',
        };
        $total = (int) $this->db->value("SELECT COUNT(*) FROM items i {$join} {$whereSql}", $params);
        $rows = $this->db->rows(
            'SELECT ' . self::ROW_COLUMNS . " FROM items i {$join} {$whereSql} ORDER BY {$order} LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params,
        );

        return ['rows' => array_map(self::row(...), $rows), 'total' => $total];
    }

    /**
     * Published items linked to a rubric directly.
     *
     * @return array{rows: list<ItemRow>, total: int}
     */
    public function rubricItems(int $rubric, string $sort, ?int $decade, int $page, int $perPage): array
    {
        $params = ['rubric' => $rubric];
        $decadeSql = '';
        if ($decade !== null) {
            $decadeSql = 'AND i.year_from BETWEEN :from AND :to';
            $params += ['from' => $decade, 'to' => $decade + 9];
        }
        $base = "FROM items i JOIN item_rubrics r ON r.item_id = i.id AND r.rubric_id = :rubric WHERE i.is_published = 1 {$decadeSql}";
        $total = (int) $this->db->value("SELECT COUNT(*) {$base}", $params);
        $rows = $this->db->rows(
            'SELECT ' . self::ROW_COLUMNS . " {$base} ORDER BY " . self::publicOrder($sort, 'r.position')
            . " LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params,
        );

        return ['rows' => array_map(self::row(...), $rows), 'total' => $total];
    }

    /**
     * Decades of the published items linked to a rubric directly.
     *
     * @return array<int, int> number of items by the first year of the decade
     */
    public function decades(int $rubric): array
    {
        $result = [];
        $rows = $this->db->rows(
            'SELECT FLOOR(i.year_from / 10) * 10 AS decade, COUNT(*) AS items
             FROM items i JOIN item_rubrics r ON r.item_id = i.id AND r.rubric_id = :rubric
             WHERE i.is_published = 1 AND i.year_from IS NOT NULL GROUP BY decade ORDER BY decade',
            ['rubric' => $rubric],
        );
        foreach ($rows as $row) {
            $result[(int) $row['decade']] = (int) $row['items'];
        }

        return $result;
    }

    /**
     * Published neighbours of an item in the authorial order of a rubric.
     *
     * @return array{prev: ?ItemRow, next: ?ItemRow}
     */
    public function neighbours(int $rubric, int $item): array
    {
        $position = $this->position($rubric, $item);
        if ($position === null) {
            return ['prev' => null, 'next' => null];
        }
        $find = function (string $cmp, string $dir) use ($rubric, $item, $position): ?array {
            $row = $this->db->row(
                'SELECT ' . self::ROW_COLUMNS . " FROM items i JOIN item_rubrics r ON r.item_id = i.id AND r.rubric_id = :rubric
                 WHERE i.is_published = 1 AND i.id <> :item AND (r.position {$cmp} :pos1 OR (r.position = :pos2 AND i.id {$cmp} :item2))
                 ORDER BY r.position {$dir}, i.id {$dir} LIMIT 1",
                ['rubric' => $rubric, 'item' => $item, 'pos1' => $position, 'pos2' => $position, 'item2' => $item],
            );

            return $row === null ? null : self::row($row);
        };

        return ['prev' => $find('<', 'DESC'), 'next' => $find('>', 'ASC')];
    }

    /**
     * Other published items of the rubric: the ones following the item in
     * the authorial order first, then from the start.
     *
     * @return list<ItemRow>
     */
    public function series(int $rubric, int $item, int $limit): array
    {
        $position = $this->position($rubric, $item) ?? 0;
        $rows = $this->db->rows(
            'SELECT ' . self::ROW_COLUMNS . ' FROM items i JOIN item_rubrics r ON r.item_id = i.id AND r.rubric_id = :rubric
             WHERE i.is_published = 1 AND i.id <> :item
             ORDER BY (r.position < :pos) , r.position, i.id LIMIT ' . $limit,
            ['rubric' => $rubric, 'item' => $item, 'pos' => $position],
        );

        return array_map(self::row(...), $rows);
    }

    /**
     * Full-text search among published items in the given rubrics; an exact
     * catalogue number comes first.
     *
     * @param list<int> $rubrics rubrics the visitor may see
     *
     * @return array{rows: list<ItemRow>, total: int}
     */
    public function search(string $query, array $rubrics, string $sort, int $page, int $perPage): array
    {
        if ($rubrics === []) {
            return ['rows' => [], 'total' => 0];
        }
        [$in, $params] = self::in($rubrics, 'r');
        $params['exact'] = $query;
        $words = self::searchWords($query);
        $boolean = implode(' ', array_map(static fn(string $w): string => '+' . $w . '*', $words));
        if ($words === []) {
            // Too short for the full-text index: the number or the start of the name
            $match = 'i.name LIKE :prefix';
            $params['prefix'] = addcslashes($query, '%_\\') . '%';
        } else {
            $match = 'MATCH (i.catno, i.name, i.summary, i.description, i.search_text) AGAINST (:query IN BOOLEAN MODE)';
            $params['query'] = $boolean;
        }
        $base = "FROM items i WHERE i.is_published = 1 AND (i.catno = :exact OR {$match})
            AND EXISTS (SELECT 1 FROM item_rubrics l WHERE l.item_id = i.id AND l.rubric_id IN ({$in}))";
        $total = (int) $this->db->value("SELECT COUNT(*) {$base}", $params);

        // An exact number first; then by relevance or by the chosen order
        $params['exact2'] = $query;
        if ($sort === 'author' && $words !== []) {
            $order = '(i.catno = :exact2) DESC, MATCH (i.catno, i.name, i.summary, i.description, i.search_text) AGAINST (:rel IN BOOLEAN MODE) DESC, i.catno_sort, i.id';
            $params['rel'] = $boolean;
        } else {
            $order = '(i.catno = :exact2) DESC, ' . self::publicOrder($sort === 'author' ? 'number' : $sort, '');
        }
        $rows = $this->db->rows(
            'SELECT ' . self::ROW_COLUMNS . " {$base} ORDER BY {$order} LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params,
        );

        return ['rows' => array_map(self::row(...), $rows), 'total' => $total];
    }

    /**
     * Number, address and order key from the form; an empty number is made
     * from the id. A new address keeps the old one for a 301 redirect.
     */
    private function assignNumber(int $id, string $catno, ?string $oldSlug): void
    {
        if ($catno === '') {
            $catno = str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            for ($n = 2; $this->catnoTaken($catno, $id); $n++) {
                $catno = str_pad((string) $id, 5, '0', STR_PAD_LEFT) . '-' . $n;
            }
        }
        $base = Slug::fromText($catno);
        if ($base === '') {
            $base = 'item-' . $id;
        }
        $slug = $base;
        for ($n = 2; $this->slugTaken($slug, $id); $n++) {
            $suffix = '-' . $n;
            $slug = rtrim(substr($base, 0, Slug::MAX_LENGTH - \strlen($suffix)), '-') . $suffix;
        }
        if ($oldSlug === $slug) {
            // The same address: keep it even if a free one would differ
        } elseif ($oldSlug !== null) {
            $this->db->run(
                'INSERT INTO slug_history (kind, old_path, target_id, created_at) VALUES (:kind, :path, :target, :created)
                 ON DUPLICATE KEY UPDATE target_id = VALUES(target_id), created_at = VALUES(created_at)',
                ['kind' => self::HISTORY_KIND, 'path' => $oldSlug, 'target' => $id, 'created' => Connection::now()],
            );
        }
        $this->db->run('DELETE FROM slug_history WHERE kind = :kind AND old_path = :path', ['kind' => self::HISTORY_KIND, 'path' => $slug]);
        $this->db->run(
            'UPDATE items SET catno = :catno, catno_sort = :sort, slug = :slug WHERE id = :id',
            ['catno' => $catno, 'sort' => self::sortKey($catno), 'slug' => $slug, 'id' => $id],
        );
    }

    /**
     * @param list<int> $rubrics in the order they were added in the form
     */
    private function saveRubrics(int $id, array $rubrics, int $main): void
    {
        $current = $this->rubricsOf($id);
        foreach (array_diff($current, $rubrics) as $gone) {
            $this->db->run('DELETE FROM item_rubrics WHERE item_id = :item AND rubric_id = :rubric', ['item' => $id, 'rubric' => $gone]);
        }
        $time = microtime(true);
        foreach (array_values(array_diff($rubrics, $current)) as $n => $rubric) {
            $this->db->run(
                'INSERT INTO item_rubrics (item_id, rubric_id, is_main, position, added_at) VALUES (:item, :rubric, 0, :position, :added)',
                [
                    'item' => $id,
                    'rubric' => $rubric,
                    'position' => $this->edgePosition($rubric, 'MIN') - self::POSITION_STEP,
                    'added' => self::microDate($time + $n * 1e-6),
                ],
            );
        }
        if (!\in_array($main, $rubrics, true)) {
            $main = $rubrics[0];
        }
        $this->db->run('UPDATE item_rubrics SET is_main = (rubric_id = :main) WHERE item_id = :item', ['main' => $main, 'item' => $id]);
    }

    /**
     * @param list<array{name: string, value: string}> $attributes
     */
    private function saveAttributes(int $id, array $attributes): void
    {
        $this->db->run('DELETE FROM item_attributes WHERE item_id = :item', ['item' => $id]);
        foreach ($attributes as $position => $attribute) {
            $this->db->run('INSERT IGNORE INTO attribute_names (name) VALUES (:name)', ['name' => $attribute['name']]);
            $nameId = (int) $this->db->value('SELECT id FROM attribute_names WHERE name = :name', ['name' => $attribute['name']]);
            $this->db->run(
                'INSERT INTO item_attributes (item_id, name_id, value, position) VALUES (:item, :name, :value, :position)',
                ['item' => $id, 'name' => $nameId, 'value' => $attribute['value'], 'position' => $position],
            );
        }
    }

    /**
     * @param list<array{number: string, finish: string, years: string}> $variants
     */
    private function saveVariants(int $id, array $variants): void
    {
        $this->db->run('DELETE FROM item_variants WHERE item_id = :item', ['item' => $id]);
        foreach ($variants as $position => $variant) {
            $this->db->run(
                'INSERT INTO item_variants (item_id, number, finish, years, position) VALUES (:item, :number, :finish, :years, :position)',
                ['item' => $id, 'position' => $position] + $variant,
            );
        }
    }

    /**
     * Removes one link; the main role passes to the next rubric by the time it was added.
     */
    private function unlink(int $item, int $rubric): void
    {
        $this->db->run('DELETE FROM item_rubrics WHERE item_id = :item AND rubric_id = :rubric', ['item' => $item, 'rubric' => $rubric]);
        if ($this->db->value('SELECT 1 FROM item_rubrics WHERE item_id = :item AND is_main = 1', ['item' => $item]) === null) {
            $this->db->run(
                'UPDATE item_rubrics SET is_main = 1 WHERE item_id = :item ORDER BY added_at, rubric_id LIMIT 1',
                ['item' => $item],
            );
        }
    }

    private function catnoTaken(string $catno, ?int $except): bool
    {
        return $this->db->value(
            'SELECT 1 FROM items WHERE catno = :catno AND id <> :id',
            ['catno' => $catno, 'id' => $except ?? 0],
        ) !== null;
    }

    private function slugTaken(string $slug, int $except): bool
    {
        return $this->db->value('SELECT 1 FROM items WHERE slug = :slug AND id <> :id', ['slug' => $slug, 'id' => $except]) !== null;
    }

    /**
     * Lowest ("MIN") or highest ("MAX") position in a rubric; 0 for an empty one.
     */
    private function edgePosition(int $rubric, string $edge): int
    {
        $value = $this->db->value("SELECT {$edge}(position) FROM item_rubrics WHERE rubric_id = :rubric", ['rubric' => $rubric]);

        return $value === null ? 0 : (int) $value;
    }

    private function position(int $rubric, int $item): ?int
    {
        $value = $this->db->value(
            'SELECT position FROM item_rubrics WHERE rubric_id = :rubric AND item_id = :item',
            ['rubric' => $rubric, 'item' => $item],
        );

        return $value === null ? null : (int) $value;
    }

    private function setPosition(int $rubric, int $item, int $position): void
    {
        $this->db->run(
            'UPDATE item_rubrics SET position = :position WHERE rubric_id = :rubric AND item_id = :item',
            ['position' => $position, 'rubric' => $rubric, 'item' => $item],
        );
    }

    /**
     * Positions the moved item has to fit between.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function neighbourPositions(int $rubric, int $item, ?int $after, ?int $before): array
    {
        $order = array_values(array_filter($this->order($rubric), static fn(int $i): bool => $i !== $item));
        if ($after !== null && \in_array($after, $order, true)) {
            $index = array_search($after, $order, true) + 1;
        } elseif ($before !== null && \in_array($before, $order, true)) {
            $index = (int) array_search($before, $order, true);
        } else {
            $index = 0;
        }
        $low = $index > 0 ? $this->position($rubric, $order[$index - 1]) : null;
        $high = isset($order[$index]) ? $this->position($rubric, $order[$index]) : null;

        return [$low, $high];
    }

    /**
     * Items of a rubric in the authorial order.
     *
     * @return list<int>
     */
    private function order(int $rubric): array
    {
        return $this->db->ints(
            'SELECT item_id FROM item_rubrics WHERE rubric_id = :rubric ORDER BY position, item_id',
            ['rubric' => $rubric],
        );
    }

    /**
     * The given items that are in the rubric, in its authorial order.
     *
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function inOrder(int $rubric, array $ids): array
    {
        return array_values(array_intersect($this->order($rubric), $ids));
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function itemsIn(int $rubric, array $ids): array
    {
        return $ids === [] ? [] : $this->inOrder($rubric, $ids);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function existingItems(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        [$in, $params] = self::in($ids);
        $found = $this->db->ints("SELECT id FROM items WHERE id IN ({$in})", $params);

        return array_values(array_intersect($ids, $found));
    }

    /**
     * Writes positions 0, STEP, 2·STEP... in the given order.
     *
     * @param list<int> $order
     */
    private function renumber(int $rubric, array $order): void
    {
        foreach (array_chunk($order, self::RENUMBER_CHUNK) as $c => $chunk) {
            $cases = '';
            $params = ['rubric' => $rubric];
            foreach ($chunk as $n => $item) {
                $cases .= " WHEN :i{$n} THEN :p{$n}";
                $params["i{$n}"] = $item;
                $params["p{$n}"] = ($c * self::RENUMBER_CHUNK + $n) * self::POSITION_STEP;
            }
            [$in, $inParams] = self::in($chunk, 'x');
            $this->db->run(
                "UPDATE item_rubrics SET position = CASE item_id{$cases} END WHERE rubric_id = :rubric AND item_id IN ({$in})",
                $params + $inParams,
            );
        }
    }

    /**
     * Order of a public list.
     */
    private static function publicOrder(string $sort, string $author): string
    {
        return match ($sort) {
            'number' => 'i.catno_sort, i.id',
            'year' => 'i.year_from IS NULL, i.year_from, i.catno_sort, i.id',
            'title' => 'i.name, i.id',
            default => $author . ', i.id',
        };
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return ItemRow
     */
    private static function row(array $row): array
    {
        $rubrics = $row['rubric_ids'] === null || $row['rubric_ids'] === '' ? [] : array_map('intval', explode(',', (string) $row['rubric_ids']));

        return [
            'id' => (int) $row['id'],
            'catno' => (string) $row['catno'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'summary' => (string) $row['summary'],
            'yearFrom' => $row['year_from'] === null ? null : (int) $row['year_from'],
            'yearTo' => $row['year_to'] === null ? null : (int) $row['year_to'],
            'published' => (bool) $row['is_published'],
            'updatedAt' => new \DateTimeImmutable((string) $row['updated_at'], new \DateTimeZone('UTC')),
            'variants' => (int) $row['variant_count'],
            'main' => $rubrics[0] ?? 0,
            'rubrics' => $rubrics,
        ];
    }

    /**
     * Placeholders for IN (...).
     *
     * @param list<int> $ids
     *
     * @return array{0: string, 1: array<string, int>}
     */
    private static function in(array $ids, string $prefix = 'id'): array
    {
        $params = [];
        foreach ($ids as $n => $id) {
            $params["{$prefix}{$n}"] = $id;
        }

        return [implode(', ', array_map(static fn(string $k): string => ':' . $k, array_keys($params))), $params];
    }

    /**
     * "ЛФЗ-1042" → "лфз-0000001042": numbers compare by value.
     */
    public static function sortKey(string $catno): string
    {
        $key = (string) preg_replace_callback('~\d+~', static fn(array $m): string => str_pad(ltrim($m[0], '0') ?: '0', 10, '0', STR_PAD_LEFT), mb_strtolower($catno));

        return mb_substr($key, 0, 191);
    }

    /**
     * Words for the full-text query: letters and digits, at least 3 characters
     * (InnoDB ignores shorter ones).
     *
     * @return list<string>
     */
    public static function searchWords(string $query): array
    {
        preg_match_all('~[\p{L}\p{N}]{3,}~u', mb_strtolower($query), $m);

        return array_values(array_unique(\array_slice($m[0], 0, 10)));
    }

    /**
     * @param ItemInput $input
     */
    private static function searchText(array $input): string
    {
        $parts = [];
        foreach ($input['attributes'] as $attribute) {
            $parts[] = $attribute['value'];
        }
        foreach ($input['variants'] as $variant) {
            $parts[] = $variant['number'] . ' ' . $variant['finish'];
        }

        return implode("\n", $parts);
    }

    private static function microDate(float $time): string
    {
        $date = \DateTimeImmutable::createFromFormat('U.u', \sprintf('%.6F', $time));

        return $date === false ? gmdate('Y-m-d H:i:s') : $date->format('Y-m-d H:i:s.u');
    }
}
