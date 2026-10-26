<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Auth;

/**
 * Password hashing: Argon2id where PHP supports it, bcrypt otherwise.
 */
final class Passwords
{
    public static function hash(string $password): string
    {
        return password_hash($password, self::algorithm());
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algorithm());
    }

    private static function algorithm(): string
    {
        return \defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }
}
