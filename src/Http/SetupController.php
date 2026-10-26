<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Http;

use Estables\Core\Paths;
use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Core\View;
use Estables\Install\Environment;
use Estables\Security\FormGuard;

/**
 * Web installer: creates the first administrator. Reachable only while no
 * administrator exists (the kernel enforces that) and only with the install
 * token that is readable on the server.
 */
final class SetupController
{
    private const PURPOSE = 'setup';

    public function __construct(
        private readonly Services $services,
        private readonly View $view,
        private readonly Paths $paths,
    ) {}

    /**
     * @param array<string, string> $params
     */
    public function handle(Request $request, array $params): Response
    {
        $state = $this->services->installState();
        $state->ensureToken();
        $guard = new FormGuard($this->services->config->string('app.secret'), $request);

        $errors = [];
        $old = [];
        if ($request->isPost()) {
            $old = [
                'install_token' => $request->input('install_token'),
                'login' => $request->input('login'),
                'display_name' => $request->input('display_name'),
            ];
            $password = $request->rawInput('password');

            if (!$guard->check(self::PURPOSE)) {
                $errors['form'] = 'Форма устарела. Проверьте поля и отправьте её ещё раз.';
            } elseif (!$state->tokenMatches($old['install_token'])) {
                $errors['install_token'] = 'Токен не подходит. Скопируйте его из файла на сервере целиком.';
            } else {
                $errors = $this->services->users()->validateNew(
                    $old['login'],
                    $password,
                    $request->rawInput('password_again'),
                    $old['display_name'],
                );
            }

            if ($errors === []) {
                $this->services->users()->create($old['login'], $password, $old['display_name']);
                $state->markInstalled();
                $this->services->logger->info("First administrator \"{$old['login']}\" created via the web installer");

                return $this->noStore(Response::html($this->view->page('setup/done', [
                    'title' => 'Установка завершена',
                    'header' => 'minimal',
                    'noindex' => true,
                    'login' => $old['login'],
                ])));
            }
        }

        $checks = new Environment($this->paths, $this->services->config, $this->services->db())->checks();
        $response = Response::html($this->view->page('setup/first-admin', [
            'title' => 'Первый администратор',
            'header' => 'minimal',
            'noindex' => true,
            'tokenFile' => $this->paths->installToken(),
            'root' => $this->paths->root,
            'formToken' => $guard->token(self::PURPOSE),
            'errors' => $errors,
            'old' => $old,
            'warnings' => Environment::warnings($checks),
        ]), $errors === [] ? 200 : 422);

        return $this->noStore($guard->apply($response));
    }

    private function noStore(Response $response): Response
    {
        return $response->setHeader('Cache-Control', 'no-store');
    }
}
