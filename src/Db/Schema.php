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
    public const VERSION = 16;

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
            case 6:
                // Build 4: items. catno_sort pads the digits of the number for natural
                // ordering; search_text gathers characteristics and variants for FULLTEXT.
                $this->db->exec('CREATE TABLE IF NOT EXISTS items (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    catno VARCHAR(64) NOT NULL,
                    catno_sort VARCHAR(191) NOT NULL,
                    slug VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    summary TEXT NOT NULL,
                    description MEDIUMTEXT NOT NULL,
                    year_from SMALLINT UNSIGNED NULL,
                    year_to SMALLINT UNSIGNED NULL,
                    is_published TINYINT(1) NOT NULL DEFAULT 0,
                    search_text MEDIUMTEXT NOT NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    updated_by INT UNSIGNED NULL,
                    UNIQUE KEY uq_items_catno (catno),
                    UNIQUE KEY uq_items_slug (slug),
                    KEY ix_items_catno_sort (catno_sort),
                    KEY ix_items_updated (updated_at),
                    FULLTEXT KEY ft_items (catno, name, summary, description, search_text),
                    CONSTRAINT fk_items_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
                ) ' . self::TABLE_OPTIONS);
                $stepDone(7);
                // no break
            case 7:
                // Build 4: items in rubrics, like tags; one link per item is the main one.
                // position: authorial order inside the rubric, with gaps.
                $this->db->exec('CREATE TABLE IF NOT EXISTS item_rubrics (
                    item_id INT UNSIGNED NOT NULL,
                    rubric_id INT UNSIGNED NOT NULL,
                    is_main TINYINT(1) NOT NULL DEFAULT 0,
                    position INT NOT NULL,
                    added_at DATETIME(6) NOT NULL,
                    PRIMARY KEY (item_id, rubric_id),
                    KEY ix_item_rubrics_order (rubric_id, position),
                    CONSTRAINT fk_item_rubrics_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE,
                    CONSTRAINT fk_item_rubrics_rubric FOREIGN KEY (rubric_id) REFERENCES rubrics (id)
                ) ' . self::TABLE_OPTIONS);
                $stepDone(8);
                // no break
            case 8:
                // Build 4: dictionary of characteristic names
                $this->db->exec('CREATE TABLE IF NOT EXISTS attribute_names (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(150) NOT NULL,
                    UNIQUE KEY uq_attribute_names (name)
                ) ' . self::TABLE_OPTIONS);
                $stepDone(9);
                // no break
            case 9:
                // Build 4: characteristics of items; (name, value) prepares value suggestions
                $this->db->exec('CREATE TABLE IF NOT EXISTS item_attributes (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    item_id INT UNSIGNED NOT NULL,
                    name_id INT UNSIGNED NOT NULL,
                    value VARCHAR(500) NOT NULL,
                    position INT NOT NULL,
                    KEY ix_item_attributes_item (item_id, position),
                    KEY ix_item_attributes_value (name_id, value(100)),
                    CONSTRAINT fk_item_attributes_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE,
                    CONSTRAINT fk_item_attributes_name FOREIGN KEY (name_id) REFERENCES attribute_names (id)
                ) ' . self::TABLE_OPTIONS);
                $stepDone(10);
                // no break
            case 10:
                // Build 4: variants of items
                $this->db->exec('CREATE TABLE IF NOT EXISTS item_variants (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    item_id INT UNSIGNED NOT NULL,
                    number VARCHAR(64) NOT NULL,
                    finish VARCHAR(500) NOT NULL,
                    years VARCHAR(64) NOT NULL,
                    position INT NOT NULL,
                    KEY ix_item_variants_item (item_id, position),
                    CONSTRAINT fk_item_variants_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE
                ) ' . self::TABLE_OPTIONS);
                $stepDone(11);
                // no break
            case 11:
                // Build 5: cleaned original images, named by the SHA-256 of the file
                $this->db->exec('CREATE TABLE IF NOT EXISTS images (
                    sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
                    ext VARCHAR(4) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                    width INT UNSIGNED NOT NULL,
                    height INT UNSIGNED NOT NULL,
                    bytes BIGINT UNSIGNED NOT NULL,
                    frames INT UNSIGNED NOT NULL DEFAULT 1,
                    source_format VARCHAR(16) NOT NULL,
                    created_at DATETIME NOT NULL,
                    KEY ix_images_created (created_at)
                ) ' . self::TABLE_OPTIONS);
                $stepDone(12);
                // no break
            case 12:
                // Build 5: photos of items; the first by position is the cover
                $this->db->exec('CREATE TABLE IF NOT EXISTS item_photos (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    item_id INT UNSIGNED NOT NULL,
                    image_sha CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                    caption VARCHAR(500) NOT NULL DEFAULT \'\',
                    position INT NOT NULL,
                    UNIQUE KEY uq_item_photos (item_id, image_sha),
                    KEY ix_item_photos_order (item_id, position),
                    KEY ix_item_photos_image (image_sha),
                    CONSTRAINT fk_item_photos_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE,
                    CONSTRAINT fk_item_photos_image FOREIGN KEY (image_sha) REFERENCES images (sha256)
                ) ' . self::TABLE_OPTIONS);
                $stepDone(13);
                // no break
            case 13:
                // Build 5: own cover of a rubric
                $this->db->exec('ALTER TABLE rubrics
                    ADD COLUMN IF NOT EXISTS cover_image CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
                    ADD FOREIGN KEY IF NOT EXISTS fk_rubrics_cover (cover_image) REFERENCES images (sha256) ON DELETE SET NULL');
                $stepDone(14);
                // no break
            case 14:
                // Build 6: who changed what. actor keeps the login as it was, so
                // the entry stays readable whatever happens to the account later.
                $this->db->exec('CREATE TABLE IF NOT EXISTS audit_log (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    created_at DATETIME(3) NOT NULL,
                    user_id INT UNSIGNED NULL,
                    actor VARCHAR(100) NOT NULL,
                    ip VARCHAR(45) CHARACTER SET ascii NOT NULL,
                    action VARCHAR(40) CHARACTER SET ascii NOT NULL,
                    object_id INT UNSIGNED NULL,
                    subject VARCHAR(500) NOT NULL,
                    details TEXT NOT NULL,
                    KEY ix_audit_created (created_at),
                    KEY ix_audit_user (user_id, created_at),
                    KEY ix_audit_action (action, created_at),
                    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
                ) ' . self::TABLE_OPTIONS);
                $stepDone(15);
                // no break
            case 15:
                // Maintenance mode: site-wide settings changed in the admin area
                $this->db->exec('CREATE TABLE IF NOT EXISTS settings (
                    name VARCHAR(64) CHARACTER SET ascii NOT NULL PRIMARY KEY,
                    value TEXT NOT NULL,
                    updated_at DATETIME NOT NULL
                ) ' . self::TABLE_OPTIONS);
                $stepDone(16);
                // no break
            default:
                // Up to date.
        }
    }
}
