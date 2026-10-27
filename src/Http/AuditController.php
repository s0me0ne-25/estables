<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Http;

use Estables\Audit\AuditLog;
use Estables\Core\Pager;
use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Core\View;

/**
 * Admin page "Журнал": who changed what, newest first.
 */
final class AuditController
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly Services $services,
        private readonly View $view,
    ) {}

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): Response
    {
        $audit = $this->services->audit();
        $actors = $audit->actors();
        $who = self::query($request, 'who');
        $what = self::query($request, 'what');
        $filter = [
            'who' => isset($actors[$who]) ? $who : '',
            'what' => isset(AuditLog::GROUPS[$what]) ? $what : '',
        ];
        $total = $audit->page($filter, 1, 1)['total'];
        $pager = Pager::fromQuery($request, $total, self::PER_PAGE);

        return Response::html($this->view->page('admin/audit', [
            'title' => 'Журнал',
            'header' => 'admin',
            'activeNav' => 'audit',
            'noindex' => true,
            'filter' => $filter,
            'actors' => $actors,
            'groups' => array_map(static fn(array $group): string => $group[0], AuditLog::GROUPS),
            'rows' => $audit->page($filter, $pager->page, self::PER_PAGE)['rows'],
            'pager' => $pager,
            'keepDays' => $this->services->config->int('audit.keep_days'),
        ]));
    }

    private static function query(Request $request, string $name): string
    {
        $value = $request->query[$name] ?? '';

        return \is_string($value) ? $value : '';
    }
}
