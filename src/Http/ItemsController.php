<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Http;

use Estables\Auth\Auth;
use Estables\Catalog\Item;
use Estables\Catalog\ItemRepository;
use Estables\Catalog\RubricException;
use Estables\Catalog\RubricTree;
use Estables\Core\HttpException;
use Estables\Core\Pager;
use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Core\Text;
use Estables\Core\View;
use Estables\Media\MediaException;

/**
 * Admin pages of items: the list with filters, bulk actions and the
 * authorial order of a rubric, and the item editor. The kernel has already
 * checked the CSRF token of every POST.
 *
 * @phpstan-import-type ItemInput from ItemRepository
 */
final class ItemsController
{
    private const LIST_PATH = '/admin/';
    private const PER_PAGE = 50;

    private const SORTS = [
        'author' => 'Авторский порядок',
        'recent' => 'Изменены недавно',
        'number' => 'По номеру',
        'title' => 'По названию',
    ];

    public function __construct(
        private readonly Services $services,
        private readonly View $view,
        private readonly Auth $auth,
    ) {}

    /**
     * The list of items.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): Response
    {
        $tree = $this->services->rubrics()->tree();
        $rubric = (int) self::query($request, 'rubric');
        if (!$tree->has($rubric)) {
            $rubric = 0;
        }
        $sort = self::query($request, 'sort');
        if (!isset(self::SORTS[$sort]) || ($sort === 'author' && $rubric === 0)) {
            $sort = $rubric > 0 ? 'author' : 'recent';
        }
        $status = self::query($request, 'status');
        $filter = [
            'q' => trim(self::query($request, 'q')),
            'rubric' => $rubric,
            'status' => \in_array($status, ['published', 'draft'], true) ? $status : '',
            'sort' => $sort,
        ];

        $items = $this->services->items();
        $total = $items->adminList($filter, 1, 1)['total'];
        $pager = Pager::fromQuery($request, $total, self::PER_PAGE);
        $list = $items->adminList($filter, $pager->page, self::PER_PAGE);

        return Response::html($this->view->page('admin/items', [
            'title' => 'Предметы',
            'header' => 'admin',
            'activeNav' => 'items',
            'noindex' => true,
            'tree' => $tree,
            'filter' => $filter,
            'sorts' => $rubric > 0 ? self::SORTS : array_diff_key(self::SORTS, ['author' => true]),
            'rows' => $list['rows'],
            'pager' => $pager,
            'totals' => $items->totals(),
            'notice' => self::notice($request),
            'query' => array_filter($filter + ['page' => $pager->page > 1 ? (string) $pager->page : ''], static fn(mixed $v): bool => $v !== '' && $v !== 0),
            'firstPosition' => ($pager->page - 1) * self::PER_PAGE + 1,
        ]));
    }

    /**
     * Bulk actions on the selected rows of the list.
     *
     * @param array<string, string> $params
     */
    public function bulk(Request $request, array $params): Response
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $request->inputList('ids')))));
        $back = self::safeBack($request->input('back'));
        if ($ids === []) {
            return Response::redirect(self::withNotice($back, ['done' => 'none']), 303);
        }
        $items = $this->services->items();
        $user = $this->auth->user()?->id;
        $rubric = (int) $request->input('rubric');
        $action = $request->input('action');
        // Each dialog has its own rubric picker
        $target = (int) $request->input($action === 'remove-rubric' ? 'target_remove' : 'target_add');
        $log = $this->services->logger;
        $actor = $this->auth->user()->login ?? '?';

        $done = match ($action) {
            'publish' => ['done' => 'published', 'n' => $items->setPublished($ids, true, $user)],
            'unpublish' => ['done' => 'unpublished', 'n' => $items->setPublished($ids, false, $user)],
            'delete' => ['done' => 'deleted', 'n' => $items->delete($ids)],
            'add-rubric' => $this->services->rubrics()->tree()->has($target)
                ? ['done' => 'added', 'n' => $items->addToRubric($ids, $target)]
                : throw new HttpException(400, 'Unknown rubric'),
            'remove-rubric' => (static function () use ($items, $ids, $target): array {
                $result = $items->removeFromRubric($ids, $target);

                return ['done' => 'removed', 'n' => $result['removed'], 'kept' => $result['kept']];
            })(),
            'top', 'bottom' => $rubric > 0
                ? (static function () use ($items, $ids, $rubric, $action): array {
                    $items->toEdge($rubric, $ids, $action === 'top');

                    return ['done' => 'ordered'];
                })()
                : throw new HttpException(400, 'No rubric'),
            'position' => $rubric > 0
                ? (static function () use ($items, $ids, $rubric, $request): array {
                    $items->toPosition($rubric, $ids, max(1, (int) $request->input('position')));

                    return ['done' => 'ordered'];
                })()
                : throw new HttpException(400, 'No rubric'),
            default => throw new HttpException(400, 'Unknown action'),
        };
        $log->info("\"{$actor}\" bulk {$action} on items " . implode(',', $ids) . ($target > 0 ? " (rubric {$target})" : ''));

        return Response::redirect(self::withNotice($back, array_map('strval', $done)), 303);
    }

    /**
     * Drag and drop in the authorial order: answers JSON {ok: true} or {ok: false, error}.
     *
     * @param array<string, string> $params
     */
    public function order(Request $request, array $params): Response
    {
        $after = (int) $request->input('after');
        $before = (int) $request->input('before');
        try {
            $this->services->items()->moveInRubric(
                (int) $request->input('rubric'),
                (int) $request->input('item'),
                $after > 0 ? $after : null,
                $before > 0 ? $before : null,
            );
        } catch (RubricException $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 409);
        }

        return Response::json(['ok' => true]);
    }

    /**
     * The editor: /admin/items/new/ or /admin/items/<id>/.
     *
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params): Response
    {
        $items = $this->services->items();
        $id = isset($params['id']) ? (int) $params['id'] : null;
        $item = $id === null ? null : $items->find($id);
        if ($id !== null && $item === null) {
            throw HttpException::notFound();
        }

        $errors = [];
        if ($request->isPost()) {
            if ($item !== null && $request->input('action') === 'delete') {
                $items->delete([$item->id]);
                $this->services->logger->info('"' . ($this->auth->user()->login ?? '?') . "\" deleted item {$item->id} ({$item->catno})");

                return Response::redirect(self::LIST_PATH . '?done=deleted&n=1', 303);
            }
            $input = self::input($request);
            $photos = self::photoInput($request);
            $errors = $items->validate($input, $item?->id);
            if ($errors === []) {
                $saved = $items->save($item?->id, $input, $this->auth->user()?->id);
                if ($item !== null) {
                    $this->services->images()->arrange($saved, $photos);
                    $this->services->rubrics()->invalidate();
                }

                return Response::redirect("/admin/items/{$saved}/?done=" . ($item === null ? 'created' : 'saved'), 303);
            }
        } elseif ($item !== null) {
            $rubrics = $items->rubricsOf($item->id);
            $input = [
                'catno' => $item->catno,
                'name' => $item->name,
                'summary' => $item->summary,
                'description' => $item->description,
                'yearFrom' => $item->yearFrom === null ? '' : (string) $item->yearFrom,
                'yearTo' => $item->yearTo === null ? '' : (string) $item->yearTo,
                'published' => $item->published,
                'rubrics' => $rubrics,
                'main' => $rubrics[0] ?? 0,
                'attributes' => $items->attributes($item->id),
                'variants' => $items->variants($item->id),
            ];
        } else {
            $tree = $this->services->rubrics()->tree();
            $rubric = (int) self::query($request, 'rubric');
            $input = [
                'catno' => '', 'name' => '', 'summary' => '', 'description' => '', 'yearFrom' => '', 'yearTo' => '',
                'published' => true, 'rubrics' => $tree->has($rubric) ? [$rubric] : [], 'main' => $rubric,
                'attributes' => [], 'variants' => [],
            ];
        }

        $tree = $this->services->rubrics()->tree();
        $done = self::query($request, 'done');
        $photoList = $item === null ? [] : $this->services->images()->photos($item->id);
        if ($request->isPost() && $item !== null) {
            // A form with errors comes back with the photos as they were arranged in it
            $byId = [];
            foreach ($photoList as $photo) {
                $byId[$photo['image']->sha] = $photo['image'];
            }
            $photoList = [];
            foreach (self::photoInput($request) as $photo) {
                if (isset($byId[$photo['sha']])) {
                    $photoList[] = ['image' => $byId[$photo['sha']], 'caption' => $photo['caption']];
                }
            }
        }

        return Response::html($this->view->page('admin/item-edit', [
            'title' => $item === null ? 'Новый предмет' : $item->catno . ' ' . $item->name,
            'header' => 'admin',
            'activeNav' => 'items',
            'noindex' => true,
            'item' => $item,
            'form' => $input,
            'errors' => $errors,
            'tree' => $tree,
            'rubricOptions' => self::rubricOptions($tree),
            'attributeNames' => $items->attributeNames(),
            'photos' => $photoList,
            'maxUploadMb' => $this->services->config->int('media.max_upload_mb'),
            'notice' => match ($done) {
                'created' => 'Предмет добавлен.',
                'saved' => 'Предмет сохранён.',
                default => null,
            },
            'publicUrl' => $item !== null && $item->published ? $item->url() : null,
        ]), $errors === [] ? 200 : 422);
    }

    /**
     * Uploads one photo of an item (the editor sends files one by one) and
     * answers JSON {ok: true, html} with the new tile, or {ok: false, error}.
     *
     * @param array<string, string> $params
     */
    public function upload(Request $request, array $params): Response
    {
        $item = $this->services->items()->find((int) $params['id']);
        if ($item === null) {
            return Response::json(['ok' => false, 'error' => 'Предмета уже нет: возможно, его удалили.'], 404);
        }
        $file = $request->file('photo');
        if ($file === null) {
            return Response::json(['ok' => false, 'error' => 'Файл не выбран.'], 422);
        }
        $error = match ($file['error']) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл больше, чем разрешает сервер (upload_max_filesize в php-fpm).',
            default => 'Файл не загрузился. Попробуйте ещё раз.',
        };
        if ($error !== null) {
            return Response::json(['ok' => false, 'error' => $error], 422);
        }
        try {
            $image = $this->services->ingest()->ingest($file['tmp'], $file['name']);
        } catch (MediaException $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        $images = $this->services->images();
        if (!$images->attach($item->id, $image)) {
            return Response::json(['ok' => false, 'error' => 'Это фото уже есть у предмета.'], 409);
        }
        $this->services->rubrics()->invalidate();
        $this->services->logger->info('"' . ($this->auth->user()->login ?? '?') . "\" added photo {$image->sha} to item {$item->id}");

        return Response::json([
            'ok' => true,
            'html' => $this->view->partial('admin/photo-tile', [
                'image' => $image,
                'caption' => '',
                'index' => \count($images->photos($item->id)) - 1,
                'item' => $item,
            ]),
        ]);
    }

    /**
     * Photos of the editor form in order: hidden photo[] and photo_caption[].
     *
     * @return list<array{sha: string, caption: string}>
     */
    private static function photoInput(Request $request): array
    {
        $captions = $request->inputList('photo_caption');
        $photos = [];
        foreach ($request->inputList('photo') as $i => $sha) {
            if (preg_match('~^[0-9a-f]{64}$~', $sha)) {
                $photos[] = ['sha' => $sha, 'caption' => $captions[$i] ?? ''];
            }
        }

        return $photos;
    }

    /**
     * @return ItemInput
     */
    private static function input(Request $request): array
    {
        $attributes = [];
        $values = $request->inputList('attr_value');
        foreach ($request->inputList('attr_name') as $i => $name) {
            $value = $values[$i] ?? '';
            if ($name !== '' || $value !== '') {
                $attributes[] = ['name' => $name, 'value' => $value];
            }
        }
        $variants = [];
        $finishes = $request->inputList('var_finish');
        $years = $request->inputList('var_years');
        foreach ($request->inputList('var_number') as $i => $number) {
            $variant = ['number' => $number, 'finish' => $finishes[$i] ?? '', 'years' => $years[$i] ?? ''];
            if (implode('', $variant) !== '') {
                $variants[] = $variant;
            }
        }
        $rubrics = array_values(array_unique(array_filter(array_map('intval', $request->inputList('rubrics')))));

        return [
            'catno' => $request->input('catno'),
            'name' => $request->input('name'),
            'summary' => self::text($request->input('summary')),
            'description' => self::text($request->input('description')),
            'yearFrom' => $request->input('year_from'),
            'yearTo' => $request->input('year_to'),
            'published' => $request->input('status') !== 'draft',
            'rubrics' => $rubrics,
            'main' => (int) $request->input('main_rubric'),
            'attributes' => $attributes,
            'variants' => $variants,
        ];
    }

    /**
     * Every rubric for the pickers: indented label and full path.
     *
     * @return list<array{id: int, label: string, path: string}>
     */
    public static function rubricOptions(RubricTree $tree): array
    {
        $options = [];
        foreach ($tree->flatten() as ['id' => $id, 'depth' => $depth]) {
            $options[] = [
                'id' => $id,
                'label' => str_repeat("\u{00A0}\u{00A0}", $depth) . $tree->node($id)['name'],
                'path' => self::rubricPath($tree, $id),
            ];
        }

        return $options;
    }

    /**
     * "Фигурки / Фарфор / Ленинградский завод".
     */
    public static function rubricPath(RubricTree $tree, int $id): string
    {
        if (!$tree->has($id)) {
            return '';
        }

        return implode(' / ', array_map(static fn(int $r): string => $tree->node($r)['name'], [...$tree->ancestors($id), $id]));
    }

    private static function text(string $value): string
    {
        return str_replace("\r\n", "\n", $value);
    }

    private static function query(Request $request, string $name): string
    {
        $value = $request->query[$name] ?? '';

        return \is_string($value) ? $value : '';
    }

    /**
     * The list address the bulk form came from; only the list itself.
     */
    private static function safeBack(string $back): string
    {
        return str_starts_with($back, self::LIST_PATH . '?') || $back === self::LIST_PATH ? $back : self::LIST_PATH;
    }

    /**
     * @param array<string, string> $notice
     */
    private static function withNotice(string $url, array $notice): string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        unset($query['done'], $query['n'], $query['kept']);

        return ($parts['path'] ?? self::LIST_PATH) . '?' . http_build_query($query + $notice);
    }

    private static function notice(Request $request): ?string
    {
        $n = (int) self::query($request, 'n');
        $items = Text::plural($n, 'предмет', 'предмета', 'предметов');

        return match (self::query($request, 'done')) {
            'published' => "Опубликовано: {$items}.",
            'unpublished' => "Снято с публикации: {$items}.",
            'deleted' => "Удалено: {$items}.",
            'added' => "Добавлено в раздел: {$items}.",
            'removed' => "Убрано из раздела: {$items}." . ((int) self::query($request, 'kept') > 0
                ? ' У ' . Text::plural((int) self::query($request, 'kept'), 'предмета', 'предметов', 'предметов') . ' этот раздел единственный, они остались в нём.'
                : ''),
            'ordered' => 'Порядок в разделе изменён.',
            'none' => 'Ничего не выбрано.',
            default => null,
        };
    }

    /**
     * Item years for a table cell.
     */
    public static function years(?int $from, ?int $to): string
    {
        return Item::yearRange($from, $to);
    }
}
