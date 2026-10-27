<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Catalog;

/**
 * A catalogue rubric (section) as stored in the database.
 */
final readonly class Rubric
{
    public function __construct(
        public int $id,
        public ?int $parentId,
        public string $slug,
        public string $name,
        /** Page heading; empty means the name */
        public string $title,
        public string $description,
        public bool $visible,
        /** SHA-256 of the own cover image, or null */
        public ?string $cover = null,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['parent_id'] === null ? null : (int) $row['parent_id'],
            (string) $row['slug'],
            (string) $row['name'],
            (string) $row['title'],
            (string) $row['description'],
            (bool) $row['is_visible'],
            isset($row['cover_image']) && \is_string($row['cover_image']) ? $row['cover_image'] : null,
        );
    }

    public function heading(): string
    {
        return $this->title !== '' ? $this->title : $this->name;
    }
}
