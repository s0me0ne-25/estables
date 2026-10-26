<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Http;

use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Core\View;

/**
 * Public catalogue pages.
 */
final class CatalogController
{
    public function __construct(private readonly View $view) {}

    /**
     * Catalogue root. Build 1: rubrics do not exist yet, so it is always empty.
     *
     * @param array<string, string> $params
     */
    public function home(Request $request, array $params): Response
    {
        return Response::html($this->view->page('pages/home', [
            'title' => 'Каталог',
            'description' => 'eStables — справочник коллекционера.',
            'activeNav' => 'catalog',
        ]));
    }
}
