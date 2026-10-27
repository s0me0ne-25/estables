<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Http;

use Estables\Catalog\RubricTree;
use Estables\Core\HttpException;
use Estables\Core\Pager;
use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Core\View;
use Estables\Security\Csp;

/**
 * Public pages of the site.
 *
 * Hidden rubrics (and everything below them) and draft items are shown to a
 * signed-in administrator only, marked as such. Guests see an item when it
 * is published and sits in at least one rubric they can see.
 *
 * Sort order: ?sort= in the address, else the es_sort cookie the visitor's
 * last choice left (set by the page script), else the authorial order.
 */
final class CatalogController
{
    public const PER_PAGE = 24;
    public const SORT_COOKIE = 'es_sort';
    public const SORTS = [
        'author' => 'Авторский порядок',
        'number' => 'По номеру в каталоге',
        'year' => 'По году выпуска',
        'title' => 'По названию',
    ];

    public function __construct(
        private readonly View $view,
        private readonly Services $services,
        private readonly bool $admin,
    ) {}

    /**
     * Catalogue root: the rubricator and the top-level rubrics.
     *
     * @param array<string, string> $params
     */
    public function home(Request $request, array $params): Response
    {
        $tree = $this->services->rubrics()->tree();

        return Response::html($this->view->page('pages/home', [
            'title' => 'Каталог',
            'description' => 'eStables — справочник коллекционера.',
            'activeNav' => 'catalog',
            'tree' => $tree,
            'top' => $this->shownChildren($tree, 0),
            'admin' => $this->admin,
        ]));
    }

    /**
     * Rubric page by its address /catalog/a/b/c/. A former address of a
     * rubric redirects to the current one.
     *
     * @param array<string, string> $params
     */
    public function rubric(Request $request, array $params): Response
    {
        $path = $params['path'];
        if (!preg_match('~^[a-z0-9-]+(?:/[a-z0-9-]+)*$~', $path)) {
            throw HttpException::notFound();
        }
        $rubrics = $this->services->rubrics();
        $tree = $rubrics->tree();
        $id = $tree->findByPath($path);
        if ($id === null) {
            $former = $rubrics->formerAddress($path);
            if ($former !== null && $tree->has($former) && $this->visible($tree, $former)) {
                return Response::redirect($tree->url($former) . self::queryString($request), 301);
            }
            throw HttpException::notFound();
        }
        $rubric = $rubrics->find($id);
        if ($rubric === null || !$this->visible($tree, $id)) {
            throw HttpException::notFound();
        }

        $items = $this->services->items();
        $sort = self::sort($request);
        $decades = $items->decades($id);
        $decade = (int) self::query($request, 'decade');
        $decade = isset($decades[$decade]) ? $decade : null;
        $total = $items->rubricItems($id, $sort, $decade, 1, 1)['total'];
        $pager = Pager::fromQuery($request, $total, self::PER_PAGE);
        $list = $items->rubricItems($id, $sort, $decade, $pager->page, self::PER_PAGE);

        return Response::html($this->view->page('pages/rubric', [
            'title' => $rubric->heading(),
            'description' => self::summary($rubric->description),
            'activeNav' => 'catalog',
            'noindex' => !$tree->isShown($id),
            'tree' => $tree,
            'rubric' => $rubric,
            'subrubrics' => $this->shownChildren($tree, $id),
            'admin' => $this->admin,
            'rows' => $list['rows'],
            'pager' => $pager,
            'sort' => $sort,
            'sorts' => self::SORTS,
            'decades' => $decades,
            'decade' => $decade,
            'directTotal' => $decade === null ? $total : $items->rubricItems($id, $sort, null, 1, 1)['total'],
            'links' => self::links($request, $tree->url($id)),
        ]));
    }

    /**
     * Item page by its address /item/<slug>/.
     *
     * @param array<string, string> $params
     */
    public function item(Request $request, array $params): Response
    {
        $slug = $params['slug'];
        $items = $this->services->items();
        $item = preg_match('~^[a-z0-9-]+$~', $slug) ? $items->findBySlug($slug) : null;
        if ($item === null) {
            $former = preg_match('~^[a-z0-9-]+$~', $slug) ? $items->formerAddress($slug) : null;
            $target = $former === null ? null : $items->find($former);
            if ($target !== null) {
                return Response::redirect($target->url(), 301);
            }
            throw HttpException::notFound();
        }
        $tree = $this->services->rubrics()->tree();
        $rubrics = array_values(array_filter($items->rubricsOf($item->id), fn(int $r): bool => $tree->has($r) && $this->visible($tree, $r)));
        $public = $item->published && array_filter($rubrics, $tree->isShown(...)) !== [];
        if (!$public && !$this->admin) {
            throw HttpException::notFound();
        }
        $main = $rubrics[0] ?? 0;
        $photos = $this->services->images()->photos($item->id);

        $response = Response::html($this->view->page('pages/item', [
            'title' => $item->catno . ' ' . $item->name,
            'description' => self::summary($item->summary !== '' ? $item->summary : $item->description),
            'activeNav' => 'catalog',
            'noindex' => !$public,
            'tree' => $tree,
            'item' => $item,
            'main' => $main,
            'others' => \array_slice($rubrics, 1),
            'attributes' => $items->attributes($item->id),
            'variants' => $items->variants($item->id),
            'neighbours' => $main > 0 ? $items->neighbours($main, $item->id) : ['prev' => null, 'next' => null],
            'series' => $main > 0 ? $items->series($main, $item->id, 4) : [],
            'photos' => $photos,
            'gallery' => $photos !== [],
            'public' => $public,
            'admin' => $this->admin,
        ]));

        // The full-screen viewer sizes its slides through style attributes;
        // <style> elements and style sheets of other sites stay forbidden.
        return $photos === [] ? $response : $response->setHeader('Content-Security-Policy', Csp::header(['style-src-attr' => ["'unsafe-inline'"]]));
    }

    /**
     * Search among the items the visitor can see.
     *
     * @param array<string, string> $params
     */
    public function search(Request $request, array $params): Response
    {
        $query = mb_substr(trim((string) preg_replace('~\s+~u', ' ', self::query($request, 'q'))), 0, 100);
        $tree = $this->services->rubrics()->tree();
        $sort = self::sort($request);
        $rows = [];
        $pager = new Pager(1, 0, self::PER_PAGE);
        if ($query !== '') {
            $rubrics = array_values(array_filter(array_column($tree->flatten(), 'id'), fn(int $id): bool => $this->visible($tree, $id)));
            $items = $this->services->items();
            $total = $items->search($query, $rubrics, $sort, 1, 1)['total'];
            $pager = Pager::fromQuery($request, $total, self::PER_PAGE);
            $rows = $items->search($query, $rubrics, $sort, $pager->page, self::PER_PAGE)['rows'];
        }

        return Response::html($this->view->page('pages/search', [
            'title' => $query === '' ? 'Поиск' : 'Поиск: ' . $query,
            'noindex' => true,
            'activeNav' => '',
            'searchQuery' => $query,
            'tree' => $tree,
            'admin' => $this->admin,
            'rows' => $rows,
            'pager' => $pager,
            'sort' => $sort,
            'sorts' => ['author' => 'По соответствию'] + self::SORTS,
            'links' => self::links($request, '/search/', ['q' => $query]),
        ]));
    }

    /**
     * /catalog/ itself is the catalogue root.
     *
     * @param array<string, string> $params
     */
    public function catalogRoot(Request $request, array $params): Response
    {
        return Response::redirect('/', 301);
    }

    /**
     * "About" page.
     *
     * @param array<string, string> $params
     */
    public function about(Request $request, array $params): Response
    {
        return Response::html($this->view->page('pages/about', [
            'title' => 'О справочнике',
            'description' => 'eStables — свободная программа для каталога коллекции.',
            'activeNav' => 'about',
        ]));
    }

    private function visible(RubricTree $tree, int $id): bool
    {
        return $this->admin || $tree->isShown($id);
    }

    /**
     * @return list<int>
     */
    private function shownChildren(RubricTree $tree, int $parent): array
    {
        return array_values(array_filter($tree->children($parent), fn(int $id): bool => $this->admin || $tree->node($id)['visible']));
    }

    private static function sort(Request $request): string
    {
        $sort = self::query($request, 'sort');
        if (!isset(self::SORTS[$sort])) {
            $sort = $request->cookies[self::SORT_COOKIE] ?? '';
        }

        return isset(self::SORTS[$sort]) ? $sort : 'author';
    }

    /**
     * Builds addresses of the same list: a page, a decade; ?sort= is kept
     * only when it was in the address.
     *
     * @param array<string, string> $base
     *
     * @return \Closure(array<string, int|string|null>): string
     */
    private static function links(Request $request, string $path, array $base = []): \Closure
    {
        $keep = $base + array_filter([
            'sort' => isset(self::SORTS[self::query($request, 'sort')]) ? self::query($request, 'sort') : '',
            'decade' => self::query($request, 'decade'),
        ]);

        return static function (array $change) use ($path, $keep): string {
            $query = http_build_query(array_filter($change + $keep, static fn(mixed $v): bool => $v !== null && $v !== ''));

            return $path . ($query === '' ? '' : '?' . $query);
        };
    }

    private static function query(Request $request, string $name): string
    {
        $value = $request->query[$name] ?? '';

        return \is_string($value) ? $value : '';
    }

    private static function queryString(Request $request): string
    {
        return $request->query === [] ? '' : '?' . http_build_query($request->query);
    }

    /**
     * The start of a text for the meta tag.
     */
    private static function summary(string $text): string
    {
        $text = trim((string) preg_replace('~\s+~u', ' ', $text));

        return mb_strlen($text) > 160 ? rtrim(mb_substr($text, 0, 157)) . '…' : $text;
    }

    /**
     * "1958, 3 варианта" for a catalogue card.
     */
    public static function cardMeta(?int $yearFrom, ?int $yearTo, int $variants): string
    {
        $parts = [];
        $year = $yearFrom ?? $yearTo;
        if ($year !== null) {
            $parts[] = (string) $year;
        }
        if ($variants > 0) {
            $parts[] = \Estables\Core\Text::plural($variants, 'вариант', 'варианта', 'вариантов');
        }

        return implode(', ', $parts);
    }
}
