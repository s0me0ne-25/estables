<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Auth;

use Estables\Core\Request;

/**
 * CSRF token for forms of a logged-in administrator.
 *
 * The token is derived from the random secret of the login session and the
 * application secret, so it needs no extra storage and stops working when
 * the session ends. Forms that work without a session use FormGuard.
 */
final class Csrf
{
    public const FIELD = '_csrf';

    public function __construct(private readonly string $appSecret) {}

    public function token(Session $session): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', 'csrf|' . $session->csrfSecret, $this->appSecret, true)), '+/', '-_'), '=');
    }

    public function check(Request $request, Session $session): bool
    {
        $sent = $request->input(self::FIELD);
        if ($sent === '') {
            $header = $request->server['HTTP_X_CSRF_TOKEN'] ?? '';
            $sent = \is_string($header) ? $header : '';
        }

        return $sent !== '' && hash_equals($this->token($session), $sent);
    }
}
