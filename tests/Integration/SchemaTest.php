<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Core\Logger;
use Estables\Db\Backup;
use Estables\Db\Schema;
use Estables\Db\SchemaException;
use Estables\Db\SchemaManager;
use Estables\Db\SchemaState;
use Estables\Install\Environment;

final class SchemaTest extends DatabaseTestCase
{
    public function testFreshInstallReachesCurrentVersion(): void
    {
        $manager = $this->manager();
        self::assertSame(0, $manager->databaseVersion());
        self::assertFalse($manager->isCachedCurrent());

        self::assertSame(SchemaState::Current, $manager->ensureCurrent());
        self::assertSame(Schema::VERSION, $manager->databaseVersion());
        self::assertTrue($manager->isCachedCurrent());
        self::assertSame(['login_attempts', 'schema_meta', 'sessions', 'users'], $this->tables());
    }

    public function testEveryStepCanRunTwice(): void
    {
        $this->manager()->ensureCurrent();
        $before = $this->showCreateTables();
        for ($from = 0; $from < Schema::VERSION; $from++) {
            new Schema($this->db)->upgrade($from, static function (int $version): void {});
        }
        self::assertSame($before, $this->showCreateTables());
    }

    public function testInterruptedUpgradeResumesFromTheFailedStep(): void
    {
        $this->manager()->ensureCurrent();
        $expected = $this->showCreateTables();
        // Pretend the run stopped after step 1: version 2 stored, later tables missing.
        $this->db->exec('DROP TABLE login_attempts');
        $this->db->exec('DROP TABLE sessions');
        $this->db->exec("UPDATE schema_meta SET value = '2' WHERE name = 'version'");

        $manager = $this->manager();
        $manager->invalidateCache();
        self::assertSame(SchemaState::Current, $manager->ensureCurrent());
        self::assertSame($expected, $this->showCreateTables());
    }

    public function testDatabaseNewerThanCodeIsReported(): void
    {
        $this->manager()->ensureCurrent();
        $this->db->exec("UPDATE schema_meta SET value = '" . (Schema::VERSION + 1) . "' WHERE name = 'version'");
        self::assertSame(SchemaState::DatabaseNewer, $this->manager()->ensureCurrent());
    }

    public function testRefusesForeignNonEmptyDatabase(): void
    {
        $this->db->exec('CREATE TABLE something_else (id INT)');
        $this->expectException(SchemaException::class);
        $this->manager()->databaseVersion();
    }

    public function testBusyWhenAnotherProcessHoldsTheLock(): void
    {
        $other = new \Estables\Db\Connection($this->config);
        $other->value("SELECT GET_LOCK('estables_schema:" . $this->config->string('db.name') . "', 0)");
        try {
            self::assertSame(SchemaState::Busy, $this->manager()->ensureCurrent());
        } finally {
            $other->value("SELECT RELEASE_LOCK('estables_schema:" . $this->config->string('db.name') . "')");
        }
    }

    public function testBackupBeforeUpgradeWhenDumpToolExists(): void
    {
        if (Environment::findBinary('mariadb-dump') === null) {
            self::markTestSkipped('mariadb-dump is not installed');
        }
        $this->manager()->ensureCurrent();
        $this->db->exec("UPDATE schema_meta SET value = '1' WHERE name = 'version'");
        $config = new \Estables\Core\Config(['backup' => ['enabled' => true]] + $this->configArray());
        $manager = new SchemaManager($this->db, $this->site->paths, new Backup($config, $this->site->paths), $this->logger());
        $manager->invalidateCache();
        self::assertSame(SchemaState::Current, $manager->ensureCurrent());
        $dumps = glob($this->site->paths->backups() . '/estables-*.sql.gz') ?: [];
        self::assertCount(1, $dumps);
        self::assertStringContainsString('CREATE TABLE `users`', (string) gzdecode((string) file_get_contents($dumps[0])));
    }

    private function manager(): SchemaManager
    {
        return new SchemaManager($this->db, $this->site->paths, new Backup($this->config, $this->site->paths), $this->logger());
    }

    private function logger(): Logger
    {
        return new Logger($this->site->paths->var() . '/log/test.log');
    }

    /**
     * @return array<string, mixed>
     */
    private function configArray(): array
    {
        return ['db' => $this->config->get('db'), 'app' => $this->config->get('app')];
    }

    /**
     * @return array<string, string>
     */
    private function showCreateTables(): array
    {
        $result = [];
        foreach ($this->tables() as $table) {
            $row = $this->db->row("SHOW CREATE TABLE `{$table}`");
            $result[$table] = (string) preg_replace('~ AUTO_INCREMENT=\d+~', '', (string) ($row['Create Table'] ?? ''));
        }

        return $result;
    }
}
