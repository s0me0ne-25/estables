<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Auth;

use Estables\Core\Config;
use Estables\Db\Connection;

/**
 * Brute-force protection for the login form.
 *
 * Failed attempts are counted in a sliding window of login.lockout_minutes,
 * separately for the login (since its last successful sign-in) and for the
 * client IP. When either count reaches its limit, the form refuses to check
 * passwords until enough failures leave the window.
 */
final class LoginThrottle
{
    /** Attempts older than this are deleted on a successful login. */
    private const KEEP_DAYS = 30;

    public function __construct(
        private readonly Connection $db,
        private readonly Config $config,
    ) {}

    /**
     * Seconds until the form accepts this login from this IP again; 0 if it does now.
     */
    public function lockedFor(string $login, string $ip): int
    {
        $window = $this->window();
        $since = gmdate('Y-m-d H:i:s', time() - $window);
        $until = 0;

        if ($login !== '') {
            $lastSuccess = $this->db->value(
                'SELECT MAX(attempted_at) FROM login_attempts WHERE login = :login AND success = 1',
                ['login' => self::cut($login)],
            );
            $from = \is_string($lastSuccess) && $lastSuccess > $since ? $lastSuccess : $since;
            $until = max($until, $this->unlockTime(
                'login = :key AND success = 0 AND attempted_at > :since',
                ['key' => self::cut($login), 'since' => $from],
                $this->config->int('login.max_failures_per_login'),
            ));
        }
        $until = max($until, $this->unlockTime(
            'ip = :key AND success = 0 AND attempted_at > :since',
            ['key' => $ip, 'since' => $since],
            $this->config->int('login.max_failures_per_ip'),
        ));

        return max(0, $until - time());
    }

    public function recordFailure(string $login, string $ip): void
    {
        $this->record($login, $ip, false);
    }

    public function recordSuccess(string $login, string $ip): void
    {
        $this->record($login, $ip, true);
        $this->db->run(
            'DELETE FROM login_attempts WHERE attempted_at < :before',
            ['before' => gmdate('Y-m-d H:i:s', time() - self::KEEP_DAYS * 86400)],
        );
    }

    /**
     * Deletes attempts older than the window that could still matter (bin/estables gc).
     */
    public function purgeOld(): int
    {
        return $this->db->run(
            'DELETE FROM login_attempts WHERE attempted_at < :before',
            ['before' => gmdate('Y-m-d H:i:s', time() - self::KEEP_DAYS * 86400)],
        )->rowCount();
    }

    /**
     * Forgets failed attempts of a login (bin/estables unlock).
     */
    public function clear(string $login): int
    {
        return $this->db->run(
            'DELETE FROM login_attempts WHERE login = :login AND success = 0',
            ['login' => self::cut($login)],
        )->rowCount();
    }

    private function record(string $login, string $ip, bool $success): void
    {
        $this->db->run(
            'INSERT INTO login_attempts (login, ip, attempted_at, success) VALUES (:login, :ip, :at, :success)',
            ['login' => self::cut($login), 'ip' => substr($ip, 0, 45), 'at' => Connection::now(), 'success' => (int) $success],
        );
    }

    /**
     * When the window drops below the limit: the moment the limit-th most
     * recent failure leaves it. 0 if the limit is not reached.
     *
     * @param array<string, string> $params
     */
    private function unlockTime(string $where, array $params, int $limit): int
    {
        $limit = max(1, $limit);
        $value = $this->db->value(
            "SELECT attempted_at FROM login_attempts WHERE {$where} ORDER BY attempted_at DESC LIMIT 1 OFFSET " . ($limit - 1),
            $params,
        );
        if (!\is_string($value)) {
            return 0;
        }

        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'))->getTimestamp() + $this->window();
    }

    private function window(): int
    {
        return $this->config->int('login.lockout_minutes') * 60;
    }

    private static function cut(string $login): string
    {
        return mb_substr($login, 0, 64);
    }
}
