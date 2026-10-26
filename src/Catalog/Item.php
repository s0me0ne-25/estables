<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Catalog;

/**
 * A catalogue item as stored in the database.
 */
final readonly class Item
{
    public function __construct(
        public int $id,
        /** Catalogue number as shown, unique on the whole site, e.g. "ЛФЗ-1042" */
        public string $catno,
        /** Address segment made from the number, e.g. "lfz-1042" */
        public string $slug,
        public string $name,
        public string $summary,
        public string $description,
        public ?int $yearFrom,
        public ?int $yearTo,
        public bool $published,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $utc = new \DateTimeZone('UTC');

        return new self(
            (int) $row['id'],
            (string) $row['catno'],
            (string) $row['slug'],
            (string) $row['name'],
            (string) $row['summary'],
            (string) $row['description'],
            $row['year_from'] === null ? null : (int) $row['year_from'],
            $row['year_to'] === null ? null : (int) $row['year_to'],
            (bool) $row['is_published'],
            new \DateTimeImmutable((string) $row['created_at'], $utc),
            new \DateTimeImmutable((string) $row['updated_at'], $utc),
        );
    }

    public function url(): string
    {
        return '/item/' . $this->slug . '/';
    }

    public function years(): string
    {
        return self::yearRange($this->yearFrom, $this->yearTo);
    }

    /**
     * "1958–1979", "1958", "до 1979" or "".
     */
    public static function yearRange(?int $from, ?int $to): string
    {
        return match (true) {
            $from !== null && $to !== null && $from !== $to => $from . '–' . $to,
            $from !== null => (string) $from,
            $to !== null => 'до ' . $to,
            default => '',
        };
    }
}
