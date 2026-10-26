<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Db;

use Estables\Core\Logger;
use Estables\Core\Paths;

/**
 * Keeps the database schema at Schema::VERSION.
 *
 * Normally "bin/estables migrate" runs after a deployment. As a safety net
 * every web request also checks a cached version number (no database query
 * when it matches) and upgrades the schema itself if needed. A named lock
 * makes sure only one process upgrades; the others get "busy".
 */
final class SchemaManager
{
    private const LOCK_PREFIX = 'estables_schema:';

    public function __construct(
        private readonly Connection $db,
        private readonly Paths $paths,
        private readonly Backup $backup,
        private readonly Logger $logger,
    ) {}

    /**
     * Cheap check on every request: compares the cached version with the code.
     */
    public function isCachedCurrent(): bool
    {
        $file = $this->cacheFile();

        return is_file($file) && trim((string) file_get_contents($file)) === (string) Schema::VERSION;
    }

    /**
     * Brings the schema up to date unless another process is doing it.
     *
     * @param int $waitSeconds how long to wait for the lock held by another process
     *
     * @throws SchemaException when the upgrade cannot run safely
     */
    public function ensureCurrent(int $waitSeconds = 0): SchemaState
    {
        $version = $this->databaseVersion();
        if ($version > Schema::VERSION) {
            return SchemaState::DatabaseNewer;
        }
        if ($version === Schema::VERSION) {
            $this->writeCache();

            return SchemaState::Current;
        }

        if (!$this->acquireLock($waitSeconds)) {
            return SchemaState::Busy;
        }
        try {
            // Re-read under the lock: another process may have finished meanwhile.
            $version = $this->databaseVersion();
            if ($version < Schema::VERSION) {
                $this->upgrade($version);
            }
        } finally {
            $this->releaseLock();
        }
        $this->writeCache();

        return SchemaState::Current;
    }

    /**
     * Version stored in the database; 0 for an empty database.
     *
     * @throws SchemaException when the database has foreign tables but no eStables schema
     */
    public function databaseVersion(): int
    {
        $tables = (int) $this->db->value(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()',
        );
        if ($tables === 0) {
            return 0;
        }
        $hasMeta = (int) $this->db->value(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_meta'",
        );
        if ($hasMeta === 0) {
            throw new SchemaException(
                'База данных «' . $this->db->databaseName() . '» не пуста, но таблиц eStables в ней нет. '
                . 'Укажите в конфигурации отдельную пустую базу.',
            );
        }

        return (int) $this->db->value("SELECT value FROM schema_meta WHERE name = 'version'");
    }

    public function invalidateCache(): void
    {
        @unlink($this->cacheFile());
    }

    private function upgrade(int $from): void
    {
        if ($from > 0 && $this->backup->enabled()) {
            try {
                $file = $this->backup->create('before-v' . Schema::VERSION);
                $this->logger->info("Schema backup written to {$file}");
            } catch (\RuntimeException $e) {
                throw new SchemaException('Не удалось сделать резервную копию базы перед обновлением схемы, обновление отменено.', 0, $e);
            }
        }

        $this->logger->info("Schema upgrade from version {$from} to " . Schema::VERSION);
        new Schema($this->db)->upgrade($from, function (int $version): void {
            $this->db->run(
                "INSERT INTO schema_meta (name, value) VALUES ('version', :v) ON DUPLICATE KEY UPDATE value = :v2",
                ['v' => (string) $version, 'v2' => (string) $version],
            );
            $this->logger->info("Schema step done, version {$version}");
        });
    }

    private function acquireLock(int $waitSeconds): bool
    {
        return (int) $this->db->value('SELECT GET_LOCK(:name, :wait)', [
            'name' => $this->lockName(),
            'wait' => $waitSeconds,
        ]) === 1;
    }

    private function releaseLock(): void
    {
        $this->db->value('SELECT RELEASE_LOCK(:name)', ['name' => $this->lockName()]);
    }

    private function lockName(): string
    {
        // Lock names are server-wide and limited to 64 characters.
        return substr(self::LOCK_PREFIX . $this->db->databaseName(), 0, 64);
    }

    private function cacheFile(): string
    {
        return $this->paths->var() . '/cache/schema.version';
    }

    private function writeCache(): void
    {
        $this->paths->writeAtomic($this->cacheFile(), (string) Schema::VERSION);
    }
}
