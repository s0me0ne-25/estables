<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Core\Config;
use Estables\Db\Connection;
use Estables\Tests\TestSite;
use PHPUnit\Framework\TestCase;

/**
 * Base for tests against a real, initially empty MariaDB database.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected TestSite $site;
    protected Config $config;
    protected Connection $db;

    protected function setUp(): void
    {
        $name = getenv('ESTABLES_TEST_DB_NAME');
        if (!\is_string($name) || $name === '') {
            self::markTestSkipped('Set ESTABLES_TEST_DB_NAME to run integration tests.');
        }
        $values = [
            'app' => ['secret' => str_repeat('t', 64), 'timezone' => 'UTC'],
            'db' => [
                'host' => self::env('ESTABLES_TEST_DB_HOST', '127.0.0.1'),
                'port' => (int) self::env('ESTABLES_TEST_DB_PORT', '3306'),
                'socket' => self::env('ESTABLES_TEST_DB_SOCKET', ''),
                'name' => $name,
                'user' => self::env('ESTABLES_TEST_DB_USER', 'root'),
                'password' => self::env('ESTABLES_TEST_DB_PASSWORD', ''),
            ],
            'backup' => ['enabled' => false],
        ];
        $this->site = new TestSite();
        $this->site->writeConfig($values);
        $this->config = new Config($values);
        $this->db = new Connection($this->config);
        $this->dropAllTables();
    }

    protected function tearDown(): void
    {
        if (isset($this->site)) {
            $this->dropAllTables();
            $this->site->remove();
        }
    }

    protected function dropAllTables(): void
    {
        $tables = $this->db->run('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')
            ->fetchAll(\PDO::FETCH_COLUMN);
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $this->db->exec('DROP TABLE `' . str_replace('`', '``', (string) $table) . '`');
        }
        $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * @return list<string>
     */
    protected function tables(): array
    {
        /** @var list<string> $tables */
        $tables = $this->db->run(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME',
        )->fetchAll(\PDO::FETCH_COLUMN);

        return $tables;
    }

    /**
     * Settings of the test site, to build a Config with overrides.
     *
     * @return array<string, mixed>
     */
    protected function configArray(): array
    {
        return ['db' => $this->config->get('db'), 'app' => $this->config->get('app')];
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);

        return \is_string($value) && $value !== '' ? $value : $default;
    }
}
