<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Auth;

/**
 * A resolved login session of an active administrator.
 */
final readonly class Session
{
    public function __construct(
        /** SHA-256 of the cookie value, raw bytes: the primary key */
        public string $idHash,
        public User $user,
        public bool $remember,
        /** Random per-session secret behind the CSRF token */
        public string $csrfSecret,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $lastSeenAt,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $rotatedAt,
    ) {}

    /**
     * Opaque handle of the session for forms (hex of the hash, never the cookie).
     */
    public function handle(): string
    {
        return bin2hex($this->idHash);
    }
}
