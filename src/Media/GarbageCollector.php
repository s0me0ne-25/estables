<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Media;

/**
 * Deletes images nobody uses any more (no item photo, no rubric cover) once
 * they are older than media.gc_delay_hours, together with all their copies,
 * and files on disk the database does not know.
 */
final class GarbageCollector
{
    public function __construct(
        private readonly ImageRepository $images,
        private readonly MediaStore $store,
        private readonly int $delayHours,
    ) {}

    /**
     * @return array{images: int, files: int}
     */
    public function run(): array
    {
        $before = time() - $this->delayHours * 3600;
        $images = 0;
        foreach ($this->images->unused(gmdate('Y-m-d H:i:s', $before)) as $image) {
            $this->images->delete($image->sha);
            $this->removeFiles($image->sha);
            $images++;
        }

        // Files without a database row: an interrupted upload, a restored backup...
        $files = 0;
        foreach (glob($this->store->root() . '/originals/*/*.*') ?: [] as $path) {
            $sha = pathinfo($path, PATHINFO_FILENAME);
            if (preg_match('~^[0-9a-f]{64}$~', $sha) && !$this->images->exists($sha) && (int) filemtime($path) < $before) {
                $files += $this->removeFiles($sha);
            }
        }
        // Copies of images that are gone
        foreach (glob($this->store->root() . '/[0-9]*/*/*.*') ?: [] as $path) {
            $sha = pathinfo($path, PATHINFO_FILENAME);
            if (preg_match('~^[0-9a-f]{64}$~', $sha) && !$this->images->exists($sha) && (int) filemtime($path) < $before) {
                $files += @unlink($path) ? 1 : 0;
            }
        }

        return ['images' => $images, 'files' => $files];
    }

    /**
     * Removes the original and every copy of an image; returns the number of files.
     */
    private function removeFiles(string $sha): int
    {
        $removed = 0;
        foreach (glob($this->store->root() . '/*/' . substr($sha, 0, 2) . '/' . $sha . '.*') ?: [] as $path) {
            $removed += @unlink($path) ? 1 : 0;
        }

        return $removed;
    }
}
