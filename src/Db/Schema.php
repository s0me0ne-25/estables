<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Db;

/**
 * Database schema history, in the style of Android's SQLiteOpenHelper.onUpgrade().
 *
 * upgrade() enters the switch at the current version and falls through every
 * later case to the end, so a fresh install (version 0) and an upgrade run
 * exactly the same steps. Case N moves the schema from version N to N + 1.
 *
 * Rules:
 * - a released step is never edited; new steps are appended before "default";
 * - VERSION is always the number of steps;
 * - a step is small (ideally one DDL statement) and safe to run twice, because
 *   MariaDB commits every DDL statement on its own: the version is stored after
 *   each step, and an interrupted upgrade resumes from the failed step;
 * - data migrations inside a step run in a transaction.
 */
final class Schema
{
    public const VERSION = 2;

    /** Table options shared by every table. */
    private const TABLE_OPTIONS = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=' . Connection::COLLATION;

    public function __construct(private readonly Connection $db) {}

    /**
     * Runs every step from $from up to VERSION.
     *
     * @param callable(int $version): void $stepDone called after each step with the version reached
     */
    public function upgrade(int $from, callable $stepDone): void
    {
        switch ($from) {
            case 0:
                // Build 1: schema metadata (the version itself lives here)
                $this->db->exec('CREATE TABLE IF NOT EXISTS schema_meta (
                    name VARCHAR(64) NOT NULL PRIMARY KEY,
                    value VARCHAR(255) NOT NULL
                ) ' . self::TABLE_OPTIONS);
                $stepDone(1);
                // no break
            case 1:
                // Build 1: administrators
                $this->db->exec('CREATE TABLE IF NOT EXISTS users (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    login VARCHAR(64) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    display_name VARCHAR(128) NOT NULL DEFAULT \'\',
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    last_login_at DATETIME NULL,
                    UNIQUE KEY uq_users_login (login)
                ) ' . self::TABLE_OPTIONS);
                $stepDone(2);
                // no break
            default:
                // Up to date.
        }
    }
}
