<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Media;

use Estables\Core\Config;
use Estables\Core\Paths;

/**
 * Where image files live and which address shows which file.
 *
 * public/media/originals/ab/<sha>.<ext>   cleaned originals
 * public/media/<size>/ab/<sha>.<ext>      smaller copies, made on the first request
 *
 * "ab" is the start of the hash: 256 folders keep each one small.
 */
final class MediaStore
{
    public function __construct(
        private readonly Paths $paths,
        private readonly Config $config,
    ) {}

    public function root(): string
    {
        return $this->paths->public() . '/media';
    }

    public function thumbSize(): int
    {
        return $this->config->int('media.thumb_size');
    }

    public function largeSize(): int
    {
        return $this->config->int('media.large_size');
    }

    /**
     * Sizes of copies the engine makes; any other size is a 404.
     *
     * @return list<int>
     */
    public function sizes(): array
    {
        return [$this->thumbSize(), $this->largeSize()];
    }

    public function originalPath(string $sha, string $ext): string
    {
        return $this->root() . '/originals/' . substr($sha, 0, 2) . '/' . $sha . '.' . $ext;
    }

    public function copyPath(int $size, string $sha, string $ext): string
    {
        return $this->root() . '/' . $size . '/' . substr($sha, 0, 2) . '/' . $sha . '.' . $ext;
    }

    public function originalUrl(Image $image): string
    {
        return '/media/originals/' . substr($image->sha, 0, 2) . '/' . $image->sha . '.' . $image->ext;
    }

    public function copyUrl(int $size, Image $image): string
    {
        return '/media/' . $size . '/' . substr($image->sha, 0, 2) . '/' . $image->sha . '.' . $image->ext;
    }

    /**
     * For cards and lists: the original when it is small, otherwise the
     * small copy; an animated GIF always gets its still first frame.
     */
    public function thumbUrl(Image $image): string
    {
        return $image->longSide() <= $this->thumbSize() && !$image->animated()
            ? $this->originalUrl($image)
            : $this->copyUrl($this->thumbSize(), $image);
    }

    /**
     * For the item page and the full-screen view: the original when it is not
     * bigger than media.original_max, otherwise the large copy.
     */
    public function largeUrl(Image $image): string
    {
        return $image->longSide() <= $this->config->int('media.original_max')
            ? $this->originalUrl($image)
            : $this->copyUrl($this->largeSize(), $image);
    }

    /**
     * Candidates for a card picture: the small copy and, for screens with
     * dense pixels, the large one. Empty when there is nothing to choose
     * (a small original, or an animated GIF whose cards stay still).
     */
    public function srcset(Image $image): string
    {
        if ($image->animated()) {
            return '';
        }
        $thumb = $this->scaledWidth($image, $this->thumbSize());
        $large = $this->scaledWidth($image, $image->longSide() <= $this->config->int('media.original_max') ? $image->longSide() : $this->largeSize());
        if ($large <= $thumb) {
            return '';
        }

        return $this->thumbUrl($image) . ' ' . $thumb . 'w, ' . $this->largeUrl($image) . ' ' . $large . 'w';
    }

    /**
     * "Скачать оригинал": through the engine, with a readable file name.
     */
    public function downloadUrl(Image $image, string $name): string
    {
        return '/download/' . $image->sha . '/' . rawurlencode($name);
    }

    /**
     * Width of a copy that fits the given size (never wider than the original).
     */
    private function scaledWidth(Image $image, int $size): int
    {
        $long = $image->longSide();

        return $long <= $size ? $image->width : max(1, (int) round($image->width * $size / $long));
    }
}
