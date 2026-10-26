<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Install;

use Estables\Core\Config;
use Estables\Core\Paths;
use Estables\Db\Connection;

/**
 * Checks that the server can run eStables. Errors block the installation,
 * warnings concern features of later builds.
 */
final class Environment
{
    public const MIN_PHP = '8.4.0';
    public const MIN_MARIADB = '10.11.0';

    /** Extensions without which the engine does not start. */
    private const REQUIRED_EXTENSIONS = ['pdo_mysql', 'mbstring', 'intl', 'fileinfo'];

    public function __construct(
        private readonly Paths $paths,
        private readonly ?Config $config = null,
        private readonly ?Connection $db = null,
    ) {}

    /**
     * @return list<Check>
     */
    public function checks(): array
    {
        $checks = [];
        $checks[] = new Check(
            'PHP ' . PHP_VERSION,
            version_compare(PHP_VERSION, self::MIN_PHP, '>=') ? Check::OK : Check::ERROR,
            'Нужен PHP ' . self::MIN_PHP . ' или новее.',
        );
        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $checks[] = new Check(
                "Расширение PHP {$extension}",
                \extension_loaded($extension) ? Check::OK : Check::ERROR,
                'Установите пакет php8.4-' . self::package($extension) . '.',
            );
        }
        $checks[] = new Check(
            'Хеширование паролей Argon2id',
            \defined('PASSWORD_ARGON2ID') ? Check::OK : Check::WARNING,
            'PHP собран без Argon2; пароли будут храниться в bcrypt.',
        );
        $checks[] = new Check(
            'Расширение PHP imagick',
            \extension_loaded('imagick') ? Check::OK : Check::WARNING,
            'Понадобится для фотографий (сборка 5): установите php8.4-imagick.',
        );
        $checks[] = new Check(
            'Утилита jpegtran',
            self::findBinary('jpegtran') !== null ? Check::OK : Check::WARNING,
            'Понадобится для очистки JPEG без потерь (сборка 5): установите libjpeg-turbo-progs.',
        );
        $checks[] = $this->writable($this->paths->var(), Check::ERROR);
        $checks[] = $this->writable($this->paths->public() . '/media', Check::WARNING);

        if ($this->config !== null) {
            $dump = $this->config->string('backup.dump_binary');
            $checks[] = new Check(
                "Утилита {$dump}",
                !$this->config->bool('backup.enabled') || self::findBinary($dump) !== null ? Check::OK : Check::ERROR,
                'Нужна для резервной копии перед обновлением схемы: установите mariadb-client или отключите backup.enabled.',
            );
        }

        if ($this->db !== null) {
            $checks[] = $this->database();
        }

        return $checks;
    }

    /**
     * @param list<Check> $checks
     *
     * @return list<Check>
     */
    public static function errors(array $checks): array
    {
        return array_values(array_filter($checks, static fn(Check $c): bool => $c->status === Check::ERROR));
    }

    /**
     * @param list<Check> $checks
     *
     * @return list<Check>
     */
    public static function warnings(array $checks): array
    {
        return array_values(array_filter($checks, static fn(Check $c): bool => $c->status === Check::WARNING));
    }

    private function database(): Check
    {
        try {
            $version = $this->db?->serverVersion() ?? '';
        } catch (\PDOException $e) {
            return new Check('Подключение к MariaDB', Check::ERROR, 'Нет подключения: ' . $e->getMessage());
        }
        $isMariaDb = stripos($version, 'mariadb') !== false;
        $number = (string) preg_replace('~^(\d+\.\d+\.\d+).*$~', '$1', $version);
        $ok = $isMariaDb && version_compare($number, self::MIN_MARIADB, '>=');

        return new Check(
            'MariaDB ' . $number,
            $ok ? Check::OK : Check::ERROR,
            $isMariaDb
                ? 'Нужна MariaDB ' . self::MIN_MARIADB . ' или новее (в Debian 13 — 11.8).'
                : 'Поддерживается только MariaDB, а не MySQL.',
        );
    }

    private function writable(string $dir, string $failStatus): Check
    {
        $ok = is_dir($dir) ? is_writable($dir) : is_writable(\dirname($dir));

        return new Check(
            'Запись в ' . substr($dir, \strlen($this->paths->root) + 1) . '/',
            $ok ? Check::OK : $failStatus,
            'Дайте пользователю php-fpm права на запись в ' . $dir . '.',
        );
    }

    private static function package(string $extension): string
    {
        return match ($extension) {
            'pdo_mysql' => 'mysql',
            'fileinfo' => 'common',
            default => $extension,
        };
    }

    public static function findBinary(string $name): ?string
    {
        if (str_contains($name, '/')) {
            return is_executable($name) ? $name : null;
        }
        $path = getenv('PATH');
        $dirs = \is_string($path) && $path !== '' ? explode(':', $path) : [];
        foreach (array_unique([...$dirs, '/usr/local/bin', '/usr/bin', '/bin']) as $dir) {
            if (is_executable($dir . '/' . $name)) {
                return $dir . '/' . $name;
            }
        }

        return null;
    }
}
