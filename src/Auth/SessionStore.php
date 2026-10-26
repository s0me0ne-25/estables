<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Auth;

use Estables\Core\Config;
use Estables\Db\Connection;

/**
 * Login sessions in the database.
 *
 * The browser holds a random 256-bit identifier; the table holds only its
 * SHA-256, so a leaked database does not yield working cookies. Logins,
 * passwords and their hashes never travel in cookies.
 *
 * Lifetime:
 * - without "remember me" the cookie lives until the browser is closed and
 *   the server ends the session after session.idle_hours without activity;
 * - with "remember me" the cookie and the server record live
 *   session.remember_days, renewed on every visit (sliding);
 * - activity is written at most every session.touch_minutes;
 * - the identifier is replaced every session.rotate_hours; the previous one
 *   stays valid for a minute so parallel requests do not log the user out.
 */
final class SessionStore
{
    public const ROTATION_GRACE_SECONDS = 60;

    public function __construct(
        private readonly Connection $db,
        private readonly Config $config,
    ) {}

    /**
     * Creates a session; returns the cookie value (the only copy of it).
     */
    public function create(User $user, bool $remember, string $ip, string $userAgent): string
    {
        $token = self::newToken();
        $now = Connection::now();
        $this->db->run(
            'INSERT INTO sessions (id_hash, user_id, remember, csrf_secret, created_at, last_seen_at, expires_at, rotated_at, ip, user_agent)
             VALUES (:hash, :user, :remember, :csrf, :created, :seen, :expires, :rotated, :ip, :agent)',
            [
                'hash' => self::hash($token),
                'user' => $user->id,
                'remember' => (int) $remember,
                'csrf' => random_bytes(32),
                'created' => $now,
                'seen' => $now,
                'expires' => $this->expiry($remember),
                'rotated' => $now,
                'ip' => substr($ip, 0, 45),
                'agent' => mb_substr($userAgent, 0, 255),
            ],
        );

        return $token;
    }

    /**
     * Finds a live session of an active user by the cookie value.
     *
     * @return array{session: Session, current: bool}|null "current" is false
     *         when the cookie holds the previous identifier within its grace period
     */
    public function find(string $token): ?array
    {
        if (!self::looksValid($token)) {
            return null;
        }
        $hash = self::hash($token);
        $now = Connection::now();
        $row = $this->db->row(
            'SELECT s.*, u.id AS u_id, u.login, u.display_name, u.is_active, u.password_hash, u.created_at AS u_created_at, u.last_login_at
             FROM sessions s JOIN users u ON u.id = s.user_id
             WHERE (s.id_hash = :hash OR (s.previous_hash = :prev AND s.previous_valid_until > :now1))
               AND s.expires_at > :now2 AND u.is_active = 1',
            ['hash' => $hash, 'prev' => $hash, 'now1' => $now, 'now2' => $now],
        );
        if ($row === null) {
            return null;
        }
        $user = User::fromRow([
            'id' => $row['u_id'],
            'login' => $row['login'],
            'display_name' => $row['display_name'],
            'is_active' => $row['is_active'],
            'password_hash' => $row['password_hash'],
            'created_at' => $row['u_created_at'],
            'last_login_at' => $row['last_login_at'],
        ]);

        return [
            'session' => new Session(
                (string) $row['id_hash'],
                $user,
                (bool) $row['remember'],
                (string) $row['csrf_secret'],
                self::date($row['created_at']),
                self::date($row['last_seen_at']),
                self::date($row['expires_at']),
                self::date($row['rotated_at']),
            ),
            'current' => hash_equals((string) $row['id_hash'], $hash),
        ];
    }

    /**
     * Whether the session is due for an activity update.
     */
    public function needsTouch(Session $session): bool
    {
        return $session->lastSeenAt->getTimestamp() + $this->config->int('session.touch_minutes') * 60 <= time();
    }

    /**
     * Records activity and extends the session.
     */
    public function touch(Session $session, string $ip): void
    {
        $this->db->run(
            'UPDATE sessions SET last_seen_at = :now, expires_at = :expires, ip = :ip WHERE id_hash = :hash',
            [
                'now' => Connection::now(),
                'expires' => $this->expiry($session->remember),
                'ip' => substr($ip, 0, 45),
                'hash' => $session->idHash,
            ],
        );
    }

    public function needsRotation(Session $session): bool
    {
        return $session->rotatedAt->getTimestamp() + $this->config->int('session.rotate_hours') * 3600 <= time();
    }

    /**
     * Replaces the identifier and records activity; returns the new cookie value.
     */
    public function rotate(Session $session, string $ip): string
    {
        $token = self::newToken();
        $now = Connection::now();
        $this->db->run(
            'UPDATE sessions SET id_hash = :new, previous_hash = :old, previous_valid_until = :grace,
                rotated_at = :rotated, last_seen_at = :seen, expires_at = :expires, ip = :ip
             WHERE id_hash = :current',
            [
                'new' => self::hash($token),
                'old' => $session->idHash,
                'grace' => gmdate('Y-m-d H:i:s', time() + self::ROTATION_GRACE_SECONDS),
                'rotated' => $now,
                'seen' => $now,
                'expires' => $this->expiry($session->remember),
                'ip' => substr($ip, 0, 45),
                'current' => $session->idHash,
            ],
        );

        return $token;
    }

    public function delete(string $idHash): void
    {
        $this->db->run('DELETE FROM sessions WHERE id_hash = :hash', ['hash' => $idHash]);
    }

    /**
     * Ends a session of the given user by its form handle; false if not found.
     */
    public function deleteByHandle(int $userId, string $handle): bool
    {
        if (!preg_match('~^[a-f0-9]{64}$~', $handle)) {
            return false;
        }

        return $this->db->run(
            'DELETE FROM sessions WHERE id_hash = :hash AND user_id = :user',
            ['hash' => (string) hex2bin($handle), 'user' => $userId],
        )->rowCount() > 0;
    }

    /**
     * Ends every session of a user, optionally keeping one.
     */
    public function deleteForUser(int $userId, ?string $exceptIdHash = null): void
    {
        $this->db->run(
            'DELETE FROM sessions WHERE user_id = :user AND id_hash <> :except',
            ['user' => $userId, 'except' => $exceptIdHash ?? str_repeat("\0", 32)],
        );
    }

    /**
     * Live sessions of a user, most recent activity first.
     *
     * @return list<array{handle: string, ip: string, userAgent: string, createdAt: \DateTimeImmutable, lastSeenAt: \DateTimeImmutable, expiresAt: \DateTimeImmutable, remember: bool}>
     */
    public function listForUser(int $userId): array
    {
        $rows = $this->db->run(
            'SELECT id_hash, ip, user_agent, created_at, last_seen_at, expires_at, remember FROM sessions
             WHERE user_id = :user AND expires_at > :now ORDER BY last_seen_at DESC',
            ['user' => $userId, 'now' => Connection::now()],
        )->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            /** @var array<string, mixed> $row */
            $result[] = [
                'handle' => bin2hex((string) $row['id_hash']),
                'ip' => (string) $row['ip'],
                'userAgent' => (string) $row['user_agent'],
                'createdAt' => self::date($row['created_at']),
                'lastSeenAt' => self::date($row['last_seen_at']),
                'expiresAt' => self::date($row['expires_at']),
                'remember' => (bool) $row['remember'],
            ];
        }

        return $result;
    }

    /**
     * Removes expired sessions.
     */
    public function purgeExpired(): int
    {
        return $this->db->run('DELETE FROM sessions WHERE expires_at <= :now', ['now' => Connection::now()])->rowCount();
    }

    /**
     * Cookie lifetime in seconds for "remember me"; null for a browser-session cookie.
     */
    public function cookieMaxAge(bool $remember): ?int
    {
        return $remember ? $this->config->int('session.remember_days') * 86400 : null;
    }

    private function expiry(bool $remember): string
    {
        $seconds = $remember
            ? $this->config->int('session.remember_days') * 86400
            : $this->config->int('session.idle_hours') * 3600;

        return gmdate('Y-m-d H:i:s', time() + $seconds);
    }

    private static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function looksValid(string $token): bool
    {
        return (bool) preg_match('~^[A-Za-z0-9_-]{43}$~', $token);
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token, true);
    }

    private static function date(mixed $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable(\is_string($value) ? $value : 'now', new \DateTimeZone('UTC'));
    }
}
