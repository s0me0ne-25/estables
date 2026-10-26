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
    public const VERSION = 6;

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
            case 2:
                // Build 2: login sessions. Only the SHA-256 of the cookie value is stored.
                $this->db->exec('CREATE TABLE IF NOT EXISTS sessions (
                    id_hash BINARY(32) NOT NULL PRIMARY KEY,
                    previous_hash BINARY(32) NULL,
                    previous_valid_until DATETIME NULL,
                    user_id INT UNSIGNED NOT NULL,
                    remember TINYINT(1) NOT NULL DEFAULT 0,
                    csrf_secret BINARY(32) NOT NULL,
                    created_at DATETIME NOT NULL,
                    last_seen_at DATETIME NOT NULL,
                    expires_at DATETIME NOT NULL,
                    rotated_at DATETIME NOT NULL,
                    ip VARCHAR(45) NOT NULL DEFAULT \'\',
                    user_agent VARCHAR(255) NOT NULL DEFAULT \'\',
                    KEY ix_sessions_previous (previous_hash),
                    KEY ix_sessions_user (user_id),
                    KEY ix_sessions_expires (expires_at),
                    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
                ) ' . self::TABLE_OPTIONS);
                $stepDone(3);
                // no break
            case 3:
                // Build 2: login attempts for brute-force protection
                $this->db->exec('CREATE TABLE IF NOT EXISTS login_attempts (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    login VARCHAR(64) NOT NULL,
                    ip VARCHAR(45) NOT NULL,
                    attempted_at DATETIME NOT NULL,
                    success TINYINT(1) NOT NULL,
                    KEY ix_login_attempts_login (login, attempted_at),
                    KEY ix_login_attempts_ip (ip, attempted_at)
                ) ' . self::TABLE_OPTIONS);
                $stepDone(4);
                // no break
            case 4:
                // Build 3: rubrics. parent_key turns the top level (NULL parent) into 0,
                // so the slug is unique among siblings at every level.
                $this->db->exec('CREATE TABLE IF NOT EXISTS rubrics (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    parent_id INT UNSIGNED NULL,
                    parent_key INT UNSIGNED AS (IFNULL(parent_id, 0)) STORED,
                    path VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                    slug VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                    name VARCHAR(150) NOT NULL,
                    title VARCHAR(255) NOT NULL DEFAULT \'\',
                    description TEXT NOT NULL,
                    position INT NOT NULL DEFAULT 0,
                    is_visible TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE KEY uq_rubrics_slug (parent_key, slug),
                    KEY ix_rubrics_parent (parent_id, position),
                    KEY ix_rubrics_path (path),
                    CONSTRAINT fk_rubrics_parent FOREIGN KEY (parent_id) REFERENCES rubrics (id)
                ) ' . self::TABLE_OPTIONS);
                $stepDone(5);
                // no break
            case 5:
                // Build 3: former addresses for 301 redirects ("rubric": slugs joined by "/")
                $this->db->exec('CREATE TABLE IF NOT EXISTS slug_history (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                    old_path VARCHAR(700) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                    target_id INT UNSIGNED NOT NULL,
                    created_at DATETIME NOT NULL,
                    UNIQUE KEY uq_slug_history_path (kind, old_path),
                    KEY ix_slug_history_target (kind, target_id)
                ) ' . self::TABLE_OPTIONS);
                $stepDone(6);
                // no break
            default:
                // Up to date.
        }
    }
}
