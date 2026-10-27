<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Media;

use Estables\Db\Connection;

/**
 * Images in the database and the photos of items.
 *
 * @phpstan-type Photo array{image: Image, caption: string}
 */
final class ImageRepository
{
    public function __construct(private readonly Connection $db) {}

    public function find(string $sha): ?Image
    {
        if (!preg_match('~^[0-9a-f]{64}$~', $sha)) {
            return null;
        }
        $row = $this->db->row('SELECT * FROM images WHERE sha256 = :sha', ['sha' => $sha]);

        return $row === null ? null : Image::fromRow($row);
    }

    /**
     * Photos of an item in order; the first is the cover.
     *
     * @return list<Photo>
     */
    public function photos(int $itemId): array
    {
        $rows = $this->db->rows(
            'SELECT im.*, p.caption FROM item_photos p JOIN images im ON im.sha256 = p.image_sha
             WHERE p.item_id = :item ORDER BY p.position, p.id',
            ['item' => $itemId],
        );

        return array_map(static fn(array $r): array => ['image' => Image::fromRow($r), 'caption' => (string) $r['caption']], $rows);
    }

    /**
     * Adds a photo at the end; false when the item already has this picture.
     */
    public function attach(int $itemId, Image $image, string $caption = ''): bool
    {
        if ($this->db->value('SELECT 1 FROM item_photos WHERE item_id = :item AND image_sha = :sha', ['item' => $itemId, 'sha' => $image->sha]) !== null) {
            return false;
        }
        $max = $this->db->value('SELECT MAX(position) FROM item_photos WHERE item_id = :item', ['item' => $itemId]);
        $this->db->run(
            'INSERT INTO item_photos (item_id, image_sha, caption, position) VALUES (:item, :sha, :caption, :position)',
            ['item' => $itemId, 'sha' => $image->sha, 'caption' => $caption, 'position' => $max === null ? 0 : (int) $max + 1],
        );

        return true;
    }

    /**
     * Saves the photo list of the editor: order, captions; photos missing
     * from the list are unlinked (the files stay until gc).
     *
     * @param list<array{sha: string, caption: string}> $photos
     */
    public function arrange(int $itemId, array $photos): void
    {
        $current = array_map(static fn(array $p): string => $p['image']->sha, $this->photos($itemId));
        $keep = [];
        foreach ($photos as $photo) {
            if (\in_array($photo['sha'], $current, true) && !isset($keep[$photo['sha']])) {
                $keep[$photo['sha']] = $photo['caption'];
            }
        }
        foreach (array_diff($current, array_keys($keep)) as $gone) {
            $this->db->run('DELETE FROM item_photos WHERE item_id = :item AND image_sha = :sha', ['item' => $itemId, 'sha' => $gone]);
        }
        $position = 0;
        foreach ($keep as $sha => $caption) {
            $this->db->run(
                'UPDATE item_photos SET caption = :caption, position = :position WHERE item_id = :item AND image_sha = :sha',
                ['caption' => mb_substr($caption, 0, 500), 'position' => $position++, 'item' => $itemId, 'sha' => (string) $sha],
            );
        }
    }

    /**
     * Images nobody uses (no item photo, no rubric cover) older than $before.
     *
     * @return list<Image>
     */
    public function unused(string $before): array
    {
        $rows = $this->db->rows(
            'SELECT im.* FROM images im
             WHERE im.created_at < :before
               AND NOT EXISTS (SELECT 1 FROM item_photos p WHERE p.image_sha = im.sha256)
               AND NOT EXISTS (SELECT 1 FROM rubrics r WHERE r.cover_image = im.sha256)',
            ['before' => $before],
        );

        return array_map(Image::fromRow(...), $rows);
    }

    /**
     * @return list<Image>
     */
    public function all(): array
    {
        return array_map(Image::fromRow(...), $this->db->rows('SELECT * FROM images ORDER BY created_at'));
    }

    public function delete(string $sha): void
    {
        $this->db->run('DELETE FROM images WHERE sha256 = :sha', ['sha' => $sha]);
    }

    public function exists(string $sha): bool
    {
        return $this->db->value('SELECT 1 FROM images WHERE sha256 = :sha', ['sha' => $sha]) !== null;
    }
}
