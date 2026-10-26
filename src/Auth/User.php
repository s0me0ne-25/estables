<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Auth;

/**
 * An administrator account.
 */
final readonly class User
{
    public function __construct(
        public int $id,
        public string $login,
        public string $displayName,
        public bool $active,
        public string $passwordHash,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $lastLoginAt,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['login'],
            (string) $row['display_name'],
            (bool) $row['is_active'],
            (string) $row['password_hash'],
            new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')),
            $row['last_login_at'] === null ? null : new \DateTimeImmutable((string) $row['last_login_at'], new \DateTimeZone('UTC')),
        );
    }

    public function name(): string
    {
        return $this->displayName !== '' ? $this->displayName : $this->login;
    }
}
