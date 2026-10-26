<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Http;

use Estables\Catalog\RubricTree;
use Estables\Core\HttpException;
use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Core\View;

/**
 * Public pages of the site. Hidden rubrics (and everything below them) are
 * shown to a signed-in administrator only, marked as hidden.
 */
final class CatalogController
{
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
                return Response::redirect($tree->url($former), 301);
            }
            throw HttpException::notFound();
        }
        $rubric = $rubrics->find($id);
        if ($rubric === null || !$this->visible($tree, $id)) {
            throw HttpException::notFound();
        }

        return Response::html($this->view->page('pages/rubric', [
            'title' => $rubric->heading(),
            'description' => self::summary($rubric->description),
            'activeNav' => 'catalog',
            'noindex' => !$tree->isShown($id),
            'tree' => $tree,
            'rubric' => $rubric,
            'subrubrics' => $this->shownChildren($tree, $id),
            'admin' => $this->admin,
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

    /**
     * The start of the description for the meta tag.
     */
    private static function summary(string $text): string
    {
        $text = trim((string) preg_replace('~\s+~u', ' ', $text));

        return mb_strlen($text) > 160 ? rtrim(mb_substr($text, 0, 157)) . '…' : $text;
    }
}
