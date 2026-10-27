<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Media;

use Estables\Core\Config;

/**
 * ImageMagick with resource limits, reading only the formats it is told to:
 * the coder is always given explicitly, so a file cannot pick a dangerous one
 * (SVG, MVG, PostScript...) by its content.
 */
final class Imaging
{
    /** Uploaded formats ImageMagick may open, by MIME type: coder and our name. */
    public const INPUT = [
        'image/jpeg' => 'JPEG',
        'image/png' => 'PNG',
        'image/gif' => 'GIF',
        'image/webp' => 'WEBP',
        'image/avif' => 'AVIF',
        'image/bmp' => 'BMP',
        'image/x-ms-bmp' => 'BMP',
        'image/tiff' => 'TIFF',
    ];

    public const CODER = ['jpg' => 'JPEG', 'png' => 'PNG', 'gif' => 'GIF'];

    private static bool $limited = false;

    public function __construct(private readonly Config $config)
    {
        if (!\extension_loaded('imagick')) {
            throw new MediaException('На сервере нет расширения PHP imagick: фотографии обрабатывать нечем.');
        }
    }

    /**
     * Width, height and number of frames without decoding the pixels.
     *
     * @return array{width: int, height: int, frames: int, orientation: int, quality: int}
     */
    public function ping(string $path, string $coder): array
    {
        $this->limit();
        $image = new \Imagick();
        try {
            $image->pingImage($coder . ':' . $path);
        } catch (\ImagickException) {
            throw new MediaException('Файл повреждён или это не изображение.');
        }
        $frames = $image->getNumberImages();
        $image->setIteratorIndex(0);
        $result = [
            'width' => $image->getImageWidth(),
            'height' => $image->getImageHeight(),
            'frames' => max(1, $frames),
            'orientation' => $image->getImageOrientation(),
            'quality' => $image->getImageCompressionQuality(),
        ];
        $image->clear();

        return $result;
    }

    /**
     * Opens a file; only the first frame unless $allFrames.
     */
    public function open(string $path, string $coder, bool $allFrames = false): \Imagick
    {
        $this->limit();
        $image = new \Imagick();
        try {
            $image->readImage($coder . ':' . $path . ($allFrames ? '' : '[0]'));
        } catch (\ImagickException) {
            throw new MediaException('Файл повреждён или это не изображение.');
        }

        return $image;
    }

    /**
     * Writes a single image with the encoding rules of the project: JPEG
     * keeps the source quality but not below 95, PNG uses compression level 9.
     */
    public static function write(\Imagick $image, string $path, string $ext, int $sourceQuality = 0): void
    {
        $image->setImageFormat(self::CODER[$ext]);
        if ($ext === 'jpg') {
            $image->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $image->setImageCompressionQuality(max(95, min(100, $sourceQuality)));
            $image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
        } elseif ($ext === 'png') {
            $image->setOption('png:compression-level', '9');
        }
        $image->writeImage(self::CODER[$ext] . ':' . $path);
    }

    /**
     * Drops everything but the colour profile.
     */
    public static function stripKeepingProfile(\Imagick $image): void
    {
        $profiles = $image->getImageProfiles('icc', true);
        $image->stripImage();
        if (isset($profiles['icc'])) {
            $image->profileImage('icc', $profiles['icc']);
        }
    }

    private function limit(): void
    {
        if (self::$limited) {
            return;
        }
        self::$limited = true;
        \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, $this->config->int('media.imagick_memory_mb') * 1048576);
        \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_MAP, $this->config->int('media.imagick_map_mb') * 1048576);
        \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_AREA, $this->config->int('media.max_megapixels') * 1000000);
    }
}
