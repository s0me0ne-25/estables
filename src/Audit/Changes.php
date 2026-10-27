<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Audit;

use Estables\Catalog\RubricTree;
use Estables\Core\Text;

/**
 * What changed between two states of an item or a rubric, as one short
 * line for the journal: "номер: ЛФЗ-1042 → ЛФЗ-1042а; название, разделы".
 *
 * @phpstan-type ItemState array{catno: string, name: string, summary: string, description: string,
 *     yearFrom: string, yearTo: string, published: bool, rubrics: list<int>, main: int,
 *     attributes: list<array{name: string, value: string}>, variants: list<array{number: string, finish: string, years: string}>}
 * @phpstan-type PhotoState list<array{sha: string, caption: string}>
 * @phpstan-type RubricState array{parent: int, name: string, title: string, slug: string, description: string, visible: bool, cover: ?string}
 */
final class Changes
{
    /**
     * @param ItemState  $before
     * @param ItemState  $after
     * @param PhotoState $photosBefore
     * @param PhotoState $photosAfter
     */
    public static function item(array $before, array $after, array $photosBefore, array $photosAfter, RubricTree $tree): string
    {
        $parts = [];
        if ($before['catno'] !== $after['catno']) {
            $parts[] = "номер: {$before['catno']} → {$after['catno']}";
        }
        if ($before['published'] !== $after['published']) {
            $parts[] = $after['published'] ? 'опубликован' : 'снят с публикации';
        }
        $fields = [
            'name' => 'название',
            'summary' => 'краткое описание',
            'description' => 'описание',
        ];
        foreach ($fields as $key => $label) {
            if ($before[$key] !== $after[$key]) {
                $parts[] = $label;
            }
        }
        if ($before['yearFrom'] !== $after['yearFrom'] || $before['yearTo'] !== $after['yearTo']) {
            $parts[] = 'годы выпуска';
        }
        $added = array_values(array_diff($after['rubrics'], $before['rubrics']));
        $removed = array_values(array_diff($before['rubrics'], $after['rubrics']));
        $name = static fn(int $id): string => $tree->has($id) ? '«' . $tree->node($id)['name'] . '»' : "№{$id}";
        if ($added !== []) {
            $parts[] = '+ ' . implode(', ', array_map($name, $added));
        }
        if ($removed !== []) {
            $parts[] = '− ' . implode(', ', array_map($name, $removed));
        }
        if ($before['main'] !== $after['main'] && $after['main'] > 0) {
            $parts[] = 'главный раздел ' . $name($after['main']);
        } elseif ($added === [] && $removed === [] && $before['rubrics'] !== $after['rubrics']) {
            $parts[] = 'порядок разделов';
        }
        if ($before['attributes'] !== $after['attributes']) {
            $parts[] = 'характеристики';
        }
        if ($before['variants'] !== $after['variants']) {
            $parts[] = 'варианты';
        }
        $photos = self::photos($photosBefore, $photosAfter);
        if ($photos !== '') {
            $parts[] = $photos;
        }

        return implode('; ', $parts);
    }

    /**
     * @param RubricState $before
     * @param RubricState $after
     */
    public static function rubric(array $before, array $after, RubricTree $tree): string
    {
        $parts = [];
        if ($before['name'] !== $after['name']) {
            $parts[] = "название: {$before['name']} → {$after['name']}";
        }
        if ($before['slug'] !== $after['slug']) {
            $parts[] = "адрес: {$before['slug']} → {$after['slug']}";
        }
        if ($before['parent'] !== $after['parent']) {
            $parts[] = 'перенесён в ' . ($after['parent'] > 0 && $tree->has($after['parent']) ? '«' . $tree->node($after['parent'])['name'] . '»' : 'верхний уровень');
        }
        if ($before['visible'] !== $after['visible']) {
            $parts[] = $after['visible'] ? 'показан' : 'скрыт';
        }
        if ($before['title'] !== $after['title']) {
            $parts[] = 'полное название';
        }
        if ($before['description'] !== $after['description']) {
            $parts[] = 'описание';
        }
        if ($before['cover'] !== $after['cover']) {
            $parts[] = $after['cover'] === null ? 'обложка убрана' : 'новая обложка';
        }

        return implode('; ', $parts);
    }

    /**
     * @param PhotoState $before
     * @param PhotoState $after
     */
    private static function photos(array $before, array $after): string
    {
        $old = array_column($before, 'sha');
        $new = array_column($after, 'sha');
        $parts = [];
        $removed = \count(array_diff($old, $new));
        if ($removed > 0) {
            $parts[] = 'убрано ' . Text::plural($removed, 'фото', 'фото', 'фото');
        }
        $kept = array_values(array_intersect($old, $new));
        if ($kept !== array_values(array_intersect($new, $old))) {
            $parts[] = 'порядок фото';
        } elseif (($old[0] ?? null) !== ($new[0] ?? null) && $new !== []) {
            $parts[] = 'обложка';
        }
        $captions = array_column($before, 'caption', 'sha');
        foreach ($after as $photo) {
            if (isset($captions[$photo['sha']]) && $captions[$photo['sha']] !== $photo['caption']) {
                $parts[] = 'подписи к фото';
                break;
            }
        }

        return implode(', ', $parts);
    }
}
