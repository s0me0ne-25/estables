<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Security;

/**
 * Content-Security-Policy header. Every asset is served by the site itself,
 * so the policy allows only 'self'; a page may add foreign sources it really
 * needs (a captcha widget on the login form).
 */
final class Csp
{
    private const BASE = [
        'default-src' => ["'self'"],
        'img-src' => ["'self'", 'data:'],
        'style-src' => ["'self'"],
        'script-src' => ["'self'"],
        'font-src' => ["'self'"],
        'connect-src' => ["'self'"],
        'frame-src' => [],
        'object-src' => ["'none'"],
        'base-uri' => ["'self'"],
        'form-action' => ["'self'"],
        'frame-ancestors' => ["'none'"],
    ];

    /**
     * @param array<string, list<string>> $extra additional sources by directive
     */
    public static function header(array $extra = []): string
    {
        $parts = [];
        foreach (self::BASE as $directive => $sources) {
            $sources = array_values(array_unique([...$sources, ...$extra[$directive] ?? []]));
            if ($sources !== []) {
                $parts[] = $directive . ' ' . implode(' ', $sources);
            }
        }

        return implode('; ', $parts);
    }
}
