<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Security;

use Estables\Core\Request;
use Estables\Core\Response;

/**
 * CSRF protection for forms that work without a login session (installer,
 * login form): a double-submit token.
 *
 * The browser gets a random value in a short-lived cookie; the form carries
 * an HMAC of that value, the form's purpose and an expiry time, signed with
 * the application secret. A foreign site can neither read the cookie nor
 * compute the HMAC, so it cannot forge a valid submission.
 */
final class FormGuard
{
    public const FIELD = '_token';
    private const TTL = 7200;

    private ?string $newCookie = null;
    private bool $forget = false;

    public function __construct(
        private readonly string $secret,
        private readonly Request $request,
    ) {}

    /**
     * Token for a hidden form field; sets the cookie on the response if needed.
     */
    public function token(string $purpose): string
    {
        $expires = time() + self::TTL;

        return $expires . '.' . $this->sign($purpose, $expires, $this->cookieValue());
    }

    public function check(string $purpose): bool
    {
        $cookie = $this->request->cookies[$this->cookieName()] ?? '';
        $parts = explode('.', $this->request->input(self::FIELD), 2);
        if ($cookie === '' || \count($parts) !== 2 || !ctype_digit($parts[0])) {
            return false;
        }
        $expires = (int) $parts[0];

        return $expires >= time() && hash_equals($this->sign($purpose, $expires, $cookie), $parts[1]);
    }

    /**
     * Drops the cookie once the form has done its job.
     */
    public function forget(): void
    {
        $this->forget = isset($this->request->cookies[$this->cookieName()]);
        $this->newCookie = null;
    }

    /**
     * Adds the cookie created during this request to the response.
     */
    public function apply(Response $response): Response
    {
        if ($this->forget) {
            $response->setCookie($this->cookieName(), '', 0, $this->request->isHttps());
        } elseif ($this->newCookie !== null) {
            $response->setCookie($this->cookieName(), $this->newCookie, self::TTL, $this->request->isHttps());
        }

        return $response;
    }

    private function cookieValue(): string
    {
        $existing = $this->request->cookies[$this->cookieName()] ?? '';
        if (preg_match('~^[a-f0-9]{32}$~', $existing)) {
            return $existing;
        }
        $this->newCookie ??= bin2hex(random_bytes(16));

        return $this->newCookie;
    }

    private function cookieName(): string
    {
        // The __Host- prefix pins the cookie to this host over HTTPS.
        return $this->request->isHttps() ? '__Host-es_form' : 'es_form';
    }

    private function sign(string $purpose, int $expires, string $cookie): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', "{$purpose}|{$expires}|{$cookie}", $this->secret, true)), '+/', '-_'), '=');
    }
}
