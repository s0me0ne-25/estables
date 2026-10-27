<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

/**
 * Configuration from config/config.php merged over built-in defaults, so an
 * older config file keeps working when later builds add new settings.
 */
final class Config
{
    /** Minimal length of the application secret, in characters. */
    public const MIN_SECRET_LENGTH = 32;

    private const DEFAULTS = [
        'app' => [
            'base_url' => '',
            'secret' => '',
            'debug' => false,
            'timezone' => 'UTC',
            'source_url' => 'https://github.com/s0me0ne-25/estables',
        ],
        'db' => [
            'host' => 'localhost',
            'port' => 3306,
            'socket' => '',
            'name' => 'estables',
            'user' => 'estables',
            'password' => '',
        ],
        'backup' => [
            'enabled' => true,
            'dump_binary' => 'mariadb-dump',
            'keep' => 10,
            'keep_daily' => 14,
        ],
        'session' => [
            'remember_days' => 30,
            'idle_hours' => 12,
            'touch_minutes' => 5,
            'rotate_hours' => 24,
        ],
        'login' => [
            'max_failures_per_login' => 5,
            'max_failures_per_ip' => 20,
            'lockout_minutes' => 15,
        ],
        'captcha' => [
            'provider' => 'stub',
            'site_key' => '',
            'secret_key' => '',
        ],
        'media' => [
            'max_upload_mb' => 50,
            'max_megapixels' => 100,
            'thumb_size' => 400,
            'large_size' => 1600,
            'original_max' => 2560,
            'jpegtran' => 'jpegtran',
            'imagick_memory_mb' => 256,
            'imagick_map_mb' => 1024,
            'gc_delay_hours' => 24,
        ],
        'audit' => [
            'keep_days' => 730,
        ],
        'security' => [
            'hsts_max_age' => 31536000,
        ],
    ];

    /** @var array<string, mixed> */
    private array $values;

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values = [])
    {
        $this->values = self::merge(self::DEFAULTS, $values);
    }

    /**
     * Loads the config file; returns null when it does not exist yet.
     */
    public static function load(string $file): ?self
    {
        if (!is_file($file)) {
            return null;
        }
        $values = require $file;
        if (!\is_array($values)) {
            throw new ConfigException('config/config.php must return an array');
        }

        /** @var array<string, mixed> $values */
        return new self($values);
    }

    /**
     * Reads a value by dotted path, e.g. "db.host".
     */
    public function get(string $path, mixed $default = null): mixed
    {
        $node = $this->values;
        foreach (explode('.', $path) as $key) {
            if (!\is_array($node) || !\array_key_exists($key, $node)) {
                return $default;
            }
            $node = $node[$key];
        }

        return $node;
    }

    public function string(string $path): string
    {
        $value = $this->get($path, '');

        return \is_scalar($value) ? (string) $value : '';
    }

    public function int(string $path): int
    {
        $value = $this->get($path, 0);

        return is_numeric($value) ? (int) $value : 0;
    }

    public function bool(string $path): bool
    {
        return (bool) $this->get($path, false);
    }

    /**
     * Human-readable problems that prevent the site from running.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];
        if (\strlen($this->string('app.secret')) < self::MIN_SECRET_LENGTH) {
            $problems[] = 'app.secret: задайте случайную строку не короче ' . self::MIN_SECRET_LENGTH
                . ' символов (например, вывод команды php -r "echo bin2hex(random_bytes(32));").';
        }
        $baseUrl = $this->string('app.base_url');
        if ($baseUrl !== '' && !preg_match('~^https?://[^/]+$~', $baseUrl)) {
            $problems[] = 'app.base_url: укажите адрес сайта без пути и завершающей косой черты, например https://example.org.';
        }
        if (!\in_array($this->string('app.timezone'), \DateTimeZone::listIdentifiers(), true)) {
            $problems[] = 'app.timezone: неизвестный часовой пояс «' . $this->string('app.timezone') . '».';
        }
        if ($this->string('db.name') === '' || $this->string('db.user') === '') {
            $problems[] = 'db.name и db.user: укажите базу данных и пользователя MariaDB.';
        }
        $provider = $this->string('captcha.provider');
        if (!\in_array($provider, ['stub', 'turnstile'], true)) {
            $problems[] = 'captcha.provider: допустимы значения «stub» и «turnstile».';
        } elseif ($provider === 'turnstile' && ($this->string('captcha.site_key') === '' || $this->string('captcha.secret_key') === '')) {
            $problems[] = 'captcha: для Cloudflare Turnstile заполните site_key и secret_key.';
        }
        foreach (['session.remember_days', 'session.idle_hours', 'session.touch_minutes', 'session.rotate_hours',
            'login.max_failures_per_login', 'login.max_failures_per_ip', 'login.lockout_minutes',
            'media.max_upload_mb', 'media.max_megapixels', 'media.thumb_size', 'media.large_size', 'media.original_max',
            'media.imagick_memory_mb', 'media.imagick_map_mb', 'media.gc_delay_hours',
            'backup.keep', 'backup.keep_daily', 'audit.keep_days'] as $key) {
            if ($this->int($key) < 1) {
                $problems[] = "{$key}: нужно целое число больше нуля.";
            }
        }

        if ($this->int('security.hsts_max_age') < 0) {
            $problems[] = 'security.hsts_max_age: нужно целое число секунд, 0 — без HSTS.';
        }
        if ($this->int('media.thumb_size') >= $this->int('media.large_size')) {
            $problems[] = 'media.thumb_size должен быть меньше media.large_size.';
        }

        return $problems;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (\is_array($value) && isset($base[$key]) && \is_array($base[$key])) {
                /** @var array<string, mixed> $nested */
                $nested = $base[$key];
                /** @var array<string, mixed> $value */
                $base[$key] = self::merge($nested, $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
