<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Auth\Captcha;

use Estables\Core\Request;

/**
 * "I am human" checkbox without any real check. For development and for
 * servers where a proof-of-work proxy (Anubis) or fail2ban stands in front
 * of the site; brute force is limited by LoginThrottle in any case.
 */
final class StubCaptcha implements Captcha
{
    public const FIELD = 'captcha_confirm';

    public function template(): string
    {
        return 'captcha/stub';
    }

    public function vars(): array
    {
        return ['field' => self::FIELD];
    }

    public function verify(Request $request): bool
    {
        return $request->input(self::FIELD) === '1';
    }

    public function cspSources(): array
    {
        return [];
    }
}
