<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Http;

use Estables\Auth\Auth;
use Estables\Auth\Passwords;
use Estables\Core\HttpException;
use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Core\Text;
use Estables\Core\View;
use Estables\Security\Csp;
use Estables\Security\FormGuard;

/**
 * Administrator sign-in and sign-out.
 */
final class AuthController
{
    private const PURPOSE = 'login';
    private const DEFAULT_TARGET = '/admin/';

    /** Hash of a throwaway value, checked when the login does not exist. */
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$ejlYdFNnV2ZlQ0RBRXN2ZQ$HSXBYSAg0jGZJVFdN/l6oOUhVB/XL8gPTdFALV9Fi7A';

    public function __construct(
        private readonly Services $services,
        private readonly View $view,
        private readonly Auth $auth,
    ) {}

    /**
     * @param array<string, string> $params
     */
    public function login(Request $request, array $params): Response
    {
        $next = self::safeTarget(\is_string($request->query['next'] ?? null) ? $request->query['next'] : '');
        if ($this->auth->session() !== null) {
            return Response::redirect($next);
        }

        $guard = new FormGuard($this->services->config->string('app.secret'), $request);
        $captcha = $this->services->captcha();
        $error = null;
        $login = '';
        $remember = false;

        if ($request->isPost()) {
            $login = $request->input('login');
            $remember = $request->input('remember') === '1';
            $error = $this->attempt($request, $guard, $captcha->verify(...), $login, $remember);
            if ($error === null) {
                $guard->forget();

                return $guard->apply(Response::redirect($next, 303));
            }
        }

        $response = Response::html($this->view->page('auth/login', [
            'title' => 'Вход администратора',
            'header' => 'minimal',
            'noindex' => true,
            'formToken' => $guard->token(self::PURPOSE),
            'action' => '/login/' . ($next !== self::DEFAULT_TARGET ? '?next=' . rawurlencode($next) : ''),
            'error' => $error,
            'login' => $login,
            'remember' => $remember,
            'rememberDays' => $this->services->config->int('session.remember_days'),
            'captchaTemplate' => $captcha->template(),
            'captchaVars' => $captcha->vars(),
        ]), $error === null ? 200 : 422);

        return $guard->apply($response)
            ->setHeader('Cache-Control', 'no-store')
            ->setHeader('Content-Security-Policy', Csp::header($captcha->cspSources()));
    }

    /**
     * @param array<string, string> $params
     */
    public function logout(Request $request, array $params): Response
    {
        $session = $this->auth->session();
        if ($session !== null) {
            if (!$this->services->csrf()->check($request, $session)) {
                throw new HttpException(403, 'CSRF');
            }
            $this->services->logger->info("Logout of \"{$session->user->login}\" from {$request->ip()}");
            $this->auth->logout();
        }

        return Response::redirect('/', 303);
    }

    /**
     * Checks the form; signs in and returns null, or returns the error text.
     *
     * @param callable(Request): bool $captchaPassed
     */
    private function attempt(Request $request, FormGuard $guard, callable $captchaPassed, string $login, bool $remember): ?string
    {
        if (!$guard->check(self::PURPOSE)) {
            return 'Форма устарела. Введите данные ещё раз.';
        }
        $ip = $request->ip();
        $throttle = $this->services->throttle();
        $locked = $throttle->lockedFor($login, $ip);
        if ($locked > 0) {
            $this->services->logger->warning("Login failed for \"{$login}\" from {$ip}: locked out");

            return 'Слишком много неудачных попыток. Попробуйте снова через ' . self::minutes($locked) . '.';
        }
        if (!$captchaPassed($request)) {
            return 'Подтвердите, что вы человек.';
        }

        $users = $this->services->users();
        $user = $login === '' ? null : $users->findByLogin($login);
        $password = $request->rawInput('password');
        // A missing login costs the same hash check as a wrong password.
        $valid = Passwords::verify($password, $user->passwordHash ?? self::DUMMY_HASH);
        if ($user === null || !$valid) {
            $throttle->recordFailure($login, $ip);
            $this->services->logger->warning("Login failed for \"{$login}\" from {$ip}");

            return 'Неверный логин или пароль.';
        }
        if (!$user->active) {
            $throttle->recordFailure($login, $ip);
            $this->services->logger->warning("Login failed for \"{$login}\" from {$ip}: account blocked");

            return 'Учётная запись заблокирована. Обратитесь к другому администратору.';
        }

        $throttle->recordSuccess($user->login, $ip);
        if (Passwords::needsRehash($user->passwordHash)) {
            $users->setPassword($user->id, $password);
        }
        $users->recordLogin($user->id);
        $this->auth->login($user, $remember);
        $this->services->logger->info("Login of \"{$user->login}\" from {$ip}" . ($remember ? ' (remembered)' : ''));

        return null;
    }

    /**
     * Where to go after signing in: a local path only, never another site.
     */
    public static function safeTarget(string $next): string
    {
        if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//') || str_contains($next, '\\')
            || preg_match('~[\x00-\x20\x7f]~', $next) || str_starts_with($next, '/login/')) {
            return self::DEFAULT_TARGET;
        }

        return $next;
    }

    private static function minutes(int $seconds): string
    {
        return Text::plural(max(1, (int) ceil($seconds / 60)), 'минуту', 'минуты', 'минут');
    }
}
