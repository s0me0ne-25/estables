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

    public function find(int $id): ?User
    {
        $row = $this->db->row('SELECT * FROM users WHERE id = :id', ['id' => $id]);

        return $row === null ? null : User::fromRow($row);
    }

    public function findByLogin(string $login): ?User
    {
        $row = $this->db->row('SELECT * FROM users WHERE login = :login', ['login' => $login]);

        return $row === null ? null : User::fromRow($row);
    }

    public function loginExists(string $login): bool
    {
        return $this->db->value('SELECT 1 FROM users WHERE login = :login', ['login' => $login]) !== null;
    }

    /**
     * Every administrator with the number of live sessions, by login.
     *
     * @return list<array{user: User, sessions: int}>
     */
    public function allWithSessions(): array
    {
        $rows = $this->db->run(
            'SELECT u.*, (SELECT COUNT(*) FROM sessions s WHERE s.user_id = u.id AND s.expires_at > :now) AS session_count
             FROM users u ORDER BY u.login',
            ['now' => Connection::now()],
        )->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            /** @var array<string, mixed> $row */
            $result[] = ['user' => User::fromRow($row), 'sessions' => (int) $row['session_count']];
        }

        return $result;
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

    public function setPassword(int $id, string $password): void
    {
        $this->db->run('UPDATE users SET password_hash = :hash WHERE id = :id', [
            'hash' => Passwords::hash($password),
            'id' => $id,
        ]);
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->run('UPDATE users SET is_active = :active WHERE id = :id', ['active' => (int) $active, 'id' => $id]);
    }

    public function recordLogin(int $id): void
    {
        $this->db->run('UPDATE users SET last_login_at = :now WHERE id = :id', ['now' => Connection::now(), 'id' => $id]);
    }

    /**
     * Validates a new account; returns messages keyed by form field.
     *
     * @return array<string, string>
     */
    public function validateNew(string $login, string $password, ?string $passwordAgain, string $displayName): array
    {
        $errors = [];
        if (!preg_match(self::LOGIN_PATTERN, $login)) {
            $errors['login'] = 'От 3 до 64 символов: латинские буквы, цифры, точка, дефис, подчёркивание.';
        } elseif ($this->loginExists($login)) {
            $errors['login'] = 'Такой логин уже есть.';
        }
        if (mb_strlen($displayName) > 128) {
            $errors['display_name'] = 'Не длиннее 128 символов.';
        }

        return $errors + self::validatePassword($password, $passwordAgain);
    }

    /**
     * @param string|null $passwordAgain confirmation; null when the user typed
     *                                   the password in clear text and needed none
     *
     * @return array<string, string>
     */
    public static function validatePassword(string $password, ?string $passwordAgain): array
    {
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return ['password' => 'Не короче ' . self::MIN_PASSWORD_LENGTH . ' символов.'];
        }
        if ($passwordAgain !== null && $password !== $passwordAgain) {
            return ['password_again' => 'Пароли не совпадают.'];
        }

        return [];
    }
}
