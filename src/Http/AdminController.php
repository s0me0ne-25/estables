<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Http;

use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Core\View;

/**
 * Admin sections that later builds fill in. The kernel lets only a signed-in
 * administrator reach any /admin/ address.
 */
final class AdminController
{
    public function __construct(private readonly View $view) {}

    /**
     * @param array<string, string> $params
     */
    public function items(Request $request, array $params): Response
    {
        return $this->placeholder('items', 'Предметы', 'Список предметов, редактор и массовые действия появятся в сборке 4.');
    }

    private function placeholder(string $nav, string $title, string $text): Response
    {
        return Response::html($this->view->page('admin/placeholder', [
            'title' => $title,
            'header' => 'admin',
            'activeNav' => $nav,
            'noindex' => true,
            'text' => $text,
        ]));
    }
}
