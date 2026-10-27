<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Http;

use Estables\Auth\Auth;
use Estables\Auth\Passwords;
use Estables\Auth\Session;
use Estables\Auth\UserRepository;
use Estables\Core\HttpException;
use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Core\View;

/**
 * Admin page "Users": administrators and the current user's sessions.
 * All changes are POSTed to the same address with an "action" field; the
 * kernel has already checked the CSRF token.
 */
final class UsersController
{
    private const PATH = '/admin/users/';

    /** Notices after a successful action, by the "done" query parameter. */
    private const NOTICES = [
        'added' => 'Администратор добавлен.',
        'password' => 'Пароль изменён.',
        'blocked' => 'Администратор заблокирован, его сессии завершены.',
        'unblocked' => 'Администратор разблокирован.',
        'sessions' => 'Сессии администратора завершены.',
        'session' => 'Сессия завершена.',
        'others' => 'Все сессии, кроме этой, завершены.',
    ];

    public function __construct(
        private readonly Services $services,
        private readonly View $view,
        private readonly Auth $auth,
    ) {}

    /**
     * @param array<string, string> $params
     */
    public function handle(Request $request, array $params): Response
    {
        $session = $this->auth->session();
        \assert($session !== null);

        $errors = [];
        $old = [];
        $openModal = null;
        if ($request->isPost()) {
            $result = $this->act($request, $session);
            if ($result instanceof Response) {
                return $result;
            }
            [$openModal, $errors, $old] = $result;
        }

        $done = \is_string($request->query['done'] ?? null) ? $request->query['done'] : '';

        return Response::html($this->view->page('admin/users', [
            'title' => 'Пользователи',
            'header' => 'admin',
            'activeNav' => 'users',
            'noindex' => true,
            'users' => $this->services->users()->allWithSessions(),
            'sessions' => self::currentFirst($this->services->sessions()->listForUser($session->user->id), $session),
            'me' => $session,
            'notice' => self::NOTICES[$done] ?? null,
            'errors' => $errors,
            'old' => $old,
            'openModal' => $openModal,
            'minPassword' => UserRepository::MIN_PASSWORD_LENGTH,
            'idleHours' => $this->services->config->int('session.idle_hours'),
        ]), $errors === [] ? 200 : 422);
    }

    /**
     * Performs the posted action.
     *
     * @return Response|array{0: string, 1: array<string, string>, 2: array<string, string>}
     *         a redirect on success, or [modal to reopen, errors by field, previous input]
     */
    private function act(Request $request, Session $me): Response|array
    {
        $users = $this->services->users();
        $sessions = $this->services->sessions();
        $audit = $this->services->audit();

        $action = $request->input('action');
        if ($action === 'end-session') {
            if ($request->input('session') === $me->handle()) {
                throw new HttpException(400, 'Use logout for the current session');
            }
            $sessions->deleteByHandle($me->user->id, $request->input('session'));
            $audit->record('user.sessions', $me->user->login, $me->user->id, 'один из своих сеансов');

            return self::done('session');
        }
        if ($action === 'end-others') {
            $sessions->deleteForUser($me->user->id, $me->idHash);
            $audit->record('user.sessions', $me->user->login, $me->user->id, 'свои сеансы, кроме текущего');

            return self::done('others');
        }
        if ($action === 'add') {
            $old = ['login' => $request->input('login'), 'display_name' => $request->input('display_name')];
            $errors = $users->validateNew($old['login'], $request->rawInput('password'), $request->passwordConfirmation(), $old['display_name']);
            if ($errors !== []) {
                return ['addUserModal', $errors, $old];
            }
            $created = $users->create($old['login'], $request->rawInput('password'), $old['display_name']);
            $audit->record('user.create', $old['login'], $created, $old['display_name']);

            return self::done('added');
        }

        $target = $users->find((int) $request->input('user'));
        if ($target === null) {
            throw new HttpException(400, 'Unknown user');
        }
        $self = $target->id === $me->user->id;

        switch ($action) {
            case 'password':
                $modal = 'passwordModal-' . $target->id;
                $endSessions = $request->input('end_sessions') === '1';
                $errors = UserRepository::validatePassword($request->rawInput('password'), $request->passwordConfirmation());
                if ($self && !Passwords::verify($request->rawInput('current_password'), $target->passwordHash)) {
                    $errors = ['current_password' => 'Текущий пароль введён неверно.'] + $errors;
                }
                if ($errors !== []) {
                    return [$modal, $errors, ['end_sessions' => $endSessions ? '1' : '0']];
                }
                $users->setPassword($target->id, $request->rawInput('password'));
                if ($endSessions) {
                    $sessions->deleteForUser($target->id, $self ? $me->idHash : null);
                }
                $audit->record('user.password', $target->login, $target->id, $endSessions ? ($self ? 'другие сеансы завершены' : 'сеансы завершены') : '');

                return self::done('password');

            case 'block':
            case 'unblock':
                if ($self) {
                    throw new HttpException(400, 'Cannot block oneself');
                }
                $users->setActive($target->id, $action === 'unblock');
                if ($action === 'block') {
                    $sessions->deleteForUser($target->id);
                }
                $audit->record('user.' . $action, $target->login, $target->id);

                return self::done($action === 'block' ? 'blocked' : 'unblocked');

            case 'end-sessions':
                $sessions->deleteForUser($target->id);
                $audit->record('user.sessions', $target->login, $target->id, 'все сеансы');
                if ($self) {
                    // Including this one: the administrator is signed out everywhere.
                    $this->auth->forgetCookie();

                    return Response::redirect('/login/', 303);
                }

                return self::done('sessions');
        }

        throw new HttpException(400, 'Unknown action');
    }

    /**
     * This device on top, the others by last activity.
     *
     * @template T of array{handle: string}
     *
     * @param list<T> $sessions
     *
     * @return list<T>
     */
    private static function currentFirst(array $sessions, Session $current): array
    {
        $mine = array_filter($sessions, static fn(array $s): bool => $s['handle'] === $current->handle());
        $others = array_filter($sessions, static fn(array $s): bool => $s['handle'] !== $current->handle());

        return [...array_values($mine), ...array_values($others)];
    }

    private static function done(string $notice): Response
    {
        // 303: the browser follows with GET, so reloading does not repeat the action.
        return Response::redirect(self::PATH . '?done=' . $notice, 303);
    }
}
