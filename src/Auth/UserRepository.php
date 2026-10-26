<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Auth;

use Estables\Db\Connection;

/**
 * Administrators. All of them are equal; there are no roles.
 */
final class UserRepository
{
    public const MIN_PASSWORD_LENGTH = 12;
    public const LOGIN_PATTERN = '~^[A-Za-z0-9._-]{3,64}$~';

    public function __construct(private readonly Connection $db) {}

    public function count(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM users');
    }

    public function loginExists(string $login): bool
    {
        return $this->db->value('SELECT 1 FROM users WHERE login = :login', ['login' => $login]) !== null;
    }

    /**
     * @return int id of the new user
     */
    public function create(string $login, string $password, string $displayName): int
    {
        $this->db->run(
            'INSERT INTO users (login, password_hash, display_name, is_active, created_at)
             VALUES (:login, :hash, :name, 1, :created)',
            [
                'login' => $login,
                'hash' => Passwords::hash($password),
                'name' => $displayName,
                'created' => Connection::now(),
            ],
        );

        return (int) $this->db->pdo()->lastInsertId();
    }

    /**
     * Validates a new account; returns messages keyed by form field.
     *
     * @return array<string, string>
     */
    public function validateNew(string $login, string $password, string $passwordAgain, string $displayName): array
    {
        $errors = [];
        if (!preg_match(self::LOGIN_PATTERN, $login)) {
            $errors['login'] = 'От 3 до 64 символов: латинские буквы, цифры, точка, дефис, подчёркивание.';
        } elseif ($this->loginExists($login)) {
            $errors['login'] = 'Такой логин уже есть.';
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors['password'] = 'Не короче ' . self::MIN_PASSWORD_LENGTH . ' символов.';
        } elseif ($password !== $passwordAgain) {
            $errors['password_again'] = 'Пароли не совпадают.';
        }
        if (mb_strlen($displayName) > 128) {
            $errors['display_name'] = 'Не длиннее 128 символов.';
        }

        return $errors;
    }
}
