<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Auth;

use Estables\Core\Request;
use Estables\Core\Response;

/**
 * Login state of one request: resolves the session cookie, keeps the session
 * alive, signs in and out. Cookie changes are collected and added to the
 * final response by apply(), whatever page it turns out to be.
 *
 * A guest without the cookie costs no database query.
 */
final class Auth
{
    private bool $resolved = false;
    private ?Session $session = null;

    /** @var array{value: string, maxAge: ?int}|null */
    private ?array $pendingCookie = null;

    public function __construct(
        private readonly SessionStore $store,
        private readonly Request $request,
    ) {}

    public function session(): ?Session
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $this->session = $this->resolve();
        }

        return $this->session;
    }

    public function user(): ?User
    {
        return $this->session()?->user;
    }

    /**
     * Starts a new session; any session this browser had is ended first.
     */
    public function login(User $user, bool $remember): Session
    {
        $previous = $this->session();
        if ($previous !== null) {
            $this->store->delete($previous->idHash);
        }
        $this->store->purgeExpired();
        $token = $this->store->create($user, $remember, $this->request->ip(), $this->userAgent());
        $this->pendingCookie = ['value' => $token, 'maxAge' => $this->store->cookieMaxAge($remember)];
        $found = $this->store->find($token);
        \assert($found !== null);
        $this->resolved = true;

        return $this->session = $found['session'];
    }

    public function logout(): void
    {
        $session = $this->session();
        if ($session !== null) {
            $this->store->delete($session->idHash);
        }
        $this->session = null;
        $this->forgetCookie();
    }

    /**
     * Drops the cookie without touching the database (the session is already gone).
     */
    public function forgetCookie(): void
    {
        $this->pendingCookie = ['value' => '', 'maxAge' => 0];
    }

    public function apply(Response $response): Response
    {
        if ($this->pendingCookie !== null) {
            $response->setCookie(
                $this->cookieName(),
                $this->pendingCookie['value'],
                $this->pendingCookie['maxAge'],
                $this->request->isHttps(),
            );
        }
        if ($this->session !== null) {
            // Pages of a signed-in administrator are personal.
            $response->defaultHeader('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    public function cookieName(): string
    {
        // The __Host- prefix pins the cookie to this host over HTTPS.
        return $this->request->isHttps() ? '__Host-es_session' : 'es_session';
    }

    private function resolve(): ?Session
    {
        $token = $this->request->cookies[$this->cookieName()] ?? '';
        if ($token === '') {
            return null;
        }
        $found = $this->store->find($token);
        if ($found === null) {
            // Strict mode: an unknown, expired or blocked session is not revived.
            $this->forgetCookie();

            return null;
        }
        $session = $found['session'];
        if (!$found['current']) {
            // The previous identifier during its grace period: a parallel
            // request racing a rotation. The browser already has the new one.
            return $session;
        }
        if ($this->store->needsRotation($session)) {
            $token = $this->store->rotate($session, $this->request->ip());
            $this->pendingCookie = ['value' => $token, 'maxAge' => $this->store->cookieMaxAge($session->remember)];
            $found = $this->store->find($token);

            return $found['session'] ?? null;
        }
        if ($this->store->needsTouch($session)) {
            $this->store->touch($session, $this->request->ip());
            if ($session->remember) {
                // Sliding expiry of a remembered cookie
                $this->pendingCookie = ['value' => $token, 'maxAge' => $this->store->cookieMaxAge(true)];
            }
        }

        return $session;
    }

    private function userAgent(): string
    {
        $ua = $this->request->server['HTTP_USER_AGENT'] ?? '';

        return \is_string($ua) ? $ua : '';
    }
}
