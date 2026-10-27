<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Media;

/**
 * A cleaned original image, named by the SHA-256 of its file.
 */
final readonly class Image
{
    public const MIME = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif'];

    public function __construct(
        public string $sha,
        /** jpg, png or gif */
        public string $ext,
        public int $width,
        public int $height,
        public int $bytes,
        /** Number of frames; more than one for an animated GIF */
        public int $frames,
        /** Format of the uploaded file before conversion, e.g. "WEBP" */
        public string $sourceFormat,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) $row['sha256'],
            (string) $row['ext'],
            (int) $row['width'],
            (int) $row['height'],
            (int) $row['bytes'],
            (int) $row['frames'],
            (string) $row['source_format'],
        );
    }

    /**
     * Columns of an image packed into one string by SQL (see PACKED), or null.
     */
    public static function unpack(?string $packed): ?self
    {
        if ($packed === null || $packed === '') {
            return null;
        }
        $parts = explode('|', $packed);
        if (\count($parts) !== 7) {
            return null;
        }

        return new self($parts[0], $parts[1], (int) $parts[2], (int) $parts[3], (int) $parts[4], (int) $parts[5], $parts[6]);
    }

    /** SQL expression that packs the columns of images alias "im" for unpack(). */
    public const PACKED = "CONCAT_WS('|', im.sha256, im.ext, im.width, im.height, im.bytes, im.frames, im.source_format)";

    public function mime(): string
    {
        return self::MIME[$this->ext] ?? 'application/octet-stream';
    }

    public function longSide(): int
    {
        return max($this->width, $this->height);
    }

    public function animated(): bool
    {
        return $this->frames > 1;
    }

    /**
     * "JPEG, 3024 × 4032, 2,4 МБ" for the download line.
     */
    public function label(): string
    {
        $format = ['jpg' => 'JPEG', 'png' => 'PNG', 'gif' => 'GIF'][$this->ext] ?? strtoupper($this->ext);
        $size = $this->bytes >= 1048576
            ? number_format($this->bytes / 1048576, 1, ',', '') . ' МБ'
            : max(1, (int) round($this->bytes / 1024)) . ' КБ';

        return "{$format}, {$this->width} × {$this->height}, {$size}";
    }
}
