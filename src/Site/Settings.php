<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Site;

use Estables\Db\Connection;

/**
 * Site-wide settings changed in the admin area (config.php holds the ones
 * set by the server owner). Read once per request at most.
 */
final class Settings
{
    private const MAINTENANCE = 'maintenance';

    /** @var array<string, string>|null */
    private ?array $values = null;

    public function __construct(private readonly Connection $db) {}

    /**
     * Maintenance mode: guests see a stub instead of the site.
     */
    public function maintenance(): bool
    {
        return ($this->all()[self::MAINTENANCE] ?? '') === '1';
    }

    public function setMaintenance(bool $on): void
    {
        $this->set(self::MAINTENANCE, $on ? '1' : '0');
    }

    private function set(string $name, string $value): void
    {
        $this->db->run(
            'INSERT INTO settings (name, value, updated_at) VALUES (:name, :value, :at)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)',
            ['name' => $name, 'value' => $value, 'at' => Connection::now()],
        );
        $this->values = null;
    }

    /**
     * @return array<string, string>
     */
    private function all(): array
    {
        if ($this->values === null) {
            $this->values = [];
            foreach ($this->db->rows('SELECT name, value FROM settings') as $row) {
                $this->values[(string) $row['name']] = (string) $row['value'];
            }
        }

        return $this->values;
    }
}
