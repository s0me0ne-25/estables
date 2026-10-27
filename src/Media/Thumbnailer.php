<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Media;

use Estables\Core\Config;
use Estables\Core\Paths;

/**
 * Makes a smaller copy of an original in the same format, never larger than
 * the original. Copies are made once, under a lock, and kept on disk; nginx
 * serves them from then on.
 *
 * - JPEG: the quality of the original, but not below 95;
 * - PNG: compression level 9;
 * - GIF: the small size is a still first frame; the large one keeps the
 *   animation (frames scaled one by one, delays and looping kept).
 */
final class Thumbnailer
{
    public function __construct(
        private readonly MediaStore $store,
        private readonly Config $config,
        private readonly Paths $paths,
    ) {}

    /**
     * Path of the copy, made now if needed.
     */
    public function copy(Image $image, int $size): string
    {
        $target = $this->store->copyPath($size, $image->sha, $image->ext);
        if (is_file($target)) {
            return $target;
        }
        $lockDir = $this->paths->ensureDir($this->paths->cache() . '/media-locks');
        $lock = fopen($lockDir . '/' . $image->sha . '-' . $size . '.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('Cannot open a media lock');
        }
        try {
            flock($lock, LOCK_EX);
            if (!is_file($target)) {
                $this->make($image, $size, $target);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            @unlink($lockDir . '/' . $image->sha . '-' . $size . '.lock');
        }

        return $target;
    }

    private function make(Image $image, int $size, string $target): void
    {
        $source = $this->store->originalPath($image->sha, $image->ext);
        if (!is_file($source)) {
            throw new MediaException('Исходный файл изображения пропал с диска.');
        }
        $this->paths->ensureDir(\dirname($target));
        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $imaging = new Imaging($this->config);
        $coder = Imaging::CODER[$image->ext];
        $animate = $image->animated() && $size !== $this->store->thumbSize();

        $picture = $imaging->open($source, $coder, $animate);
        try {
            if ($animate) {
                $frames = $picture->coalesceImages();
                foreach ($frames as $frame) {
                    self::fit($frame, $size);
                }
                $optimized = $frames->deconstructImages();
                $optimized->setImageFormat('GIF');
                $optimized->writeImages('GIF:' . $tmp, true);
                $frames->clear();
                $optimized->clear();
            } else {
                $quality = $picture->getImageCompressionQuality();
                self::fit($picture, $size);
                Imaging::stripKeepingProfile($picture);
                Imaging::write($picture, $tmp, $image->ext, $quality);
            }
        } catch (\ImagickException $e) {
            @unlink($tmp);

            throw new \RuntimeException("Cannot make a {$size}px copy of {$image->sha}: " . $e->getMessage(), 0, $e);
        } finally {
            $picture->clear();
        }
        @chmod($tmp, 0o664);
        if (!rename($tmp, $target)) {
            @unlink($tmp);

            throw new \RuntimeException("Cannot store {$target}");
        }
    }

    /**
     * Scales down to fit $size × $size; never up.
     */
    private static function fit(\Imagick $image, int $size): void
    {
        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        if (max($width, $height) <= $size) {
            return;
        }
        $image->resizeImage($size, $size, \Imagick::FILTER_LANCZOS, 1, true);
        $image->setImagePage(0, 0, 0, 0);
    }
}
