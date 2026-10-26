<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Db;

use Estables\Core\Config;

/**
 * Lazily opened PDO connection to MariaDB with the project's session settings:
 * utf8mb4, UTC timestamps, strict SQL mode, real prepared statements.
 */
final class Connection
{
    public const COLLATION = 'utf8mb4_uca1400_ai_ci';

    private ?\PDO $pdo = null;

    public function __construct(private readonly Config $config) {}

    public function pdo(): \PDO
    {
        if ($this->pdo === null) {
            $socket = $this->config->string('db.socket');
            $dsn = $socket !== ''
                ? 'mysql:unix_socket=' . $socket
                : 'mysql:host=' . $this->config->string('db.host') . ';port=' . $this->config->int('db.port');
            $dsn .= ';dbname=' . $this->config->string('db.name') . ';charset=utf8mb4';

            $pdo = new \PDO($dsn, $this->config->string('db.user'), $this->config->string('db.password'), [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
            $pdo->exec('SET NAMES utf8mb4 COLLATE ' . self::COLLATION
                . ", time_zone = '+00:00'"
                . ", sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
            $this->pdo = $pdo;
        }

        return $this->pdo;
    }

    /**
     * @param array<string|int, scalar|null> $params
     */
    public function run(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * First column of the first row, or null.
     *
     * @param array<string|int, scalar|null> $params
     */
    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * @param array<string|int, scalar|null> $params
     *
     * @return array<string, mixed>|null
     */
    public function row(string $sql, array $params = []): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * All rows of a query.
     *
     * @param array<string|int, scalar|null> $params
     *
     * @return list<array<string, mixed>>
     */
    public function rows(string $sql, array $params = []): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->run($sql, $params)->fetchAll();

        return $rows;
    }

    /**
     * The first column of every row as integers (ids).
     *
     * @param array<string|int, scalar|null> $params
     *
     * @return list<int>
     */
    public function ints(string $sql, array $params = []): array
    {
        return array_values(array_map('intval', $this->run($sql, $params)->fetchAll(\PDO::FETCH_COLUMN)));
    }

    /**
     * Executes a statement without parameters (DDL and the like).
     */
    public function exec(string $sql): void
    {
        $this->pdo()->exec($sql);
    }

    /**
     * Runs the callback in a transaction; rolls back on any exception. Inside
     * an open transaction the callback simply joins it.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        if ($pdo->inTransaction()) {
            return $callback();
        }
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            try {
                $pdo->rollBack();
            } catch (\PDOException) {
                // Already ended (MariaDB commits DDL statements implicitly)
            }

            throw $e;
        }
    }

    public function databaseName(): string
    {
        return $this->config->string('db.name');
    }

    public function serverVersion(): string
    {
        return (string) $this->value('SELECT VERSION()');
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
