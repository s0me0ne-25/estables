<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Media;

use Estables\Core\Config;
use Estables\Core\Logger;
use Estables\Db\Connection;
use Estables\Install\Environment;

/**
 * Turns an uploaded file into a cleaned original:
 *
 * - JPEG, PNG and GIF keep their format; only metadata is removed (JPEG with
 *   jpegtran, which also applies the EXIF rotation losslessly; PNG and GIF by
 *   dropping blocks);
 * - other formats (WebP, AVIF, BMP, TIFF) become JPEG: first frame,
 *   transparency on white, quality 95; that JPEG is the original;
 * - the file is named by the SHA-256 of the cleaned content, so the same
 *   picture uploaded twice (even with different metadata) is stored once.
 */
final class Ingest
{
    /** EXIF orientation → lossless jpegtran transformation. */
    private const JPEGTRAN_ROTATION = [
        2 => ['-flip', 'horizontal'],
        3 => ['-rotate', '180'],
        4 => ['-flip', 'vertical'],
        5 => ['-transpose'],
        6 => ['-rotate', '90'],
        7 => ['-transverse'],
        8 => ['-rotate', '270'],
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly MediaStore $store,
        private readonly Config $config,
        private readonly Logger $logger,
    ) {}

    /**
     * @param string $path    the uploaded file; it is not modified
     * @param string $name    file name as sent by the browser, for messages
     */
    public function ingest(string $path, string $name = ''): Image
    {
        $bytes = (int) @filesize($path);
        if ($bytes === 0) {
            throw new MediaException('Файл пустой.');
        }
        if ($bytes > $this->config->int('media.max_upload_mb') * 1048576) {
            throw new MediaException('Файл больше ' . $this->config->int('media.max_upload_mb') . ' МБ.');
        }
        $mime = (string) new \finfo(FILEINFO_MIME_TYPE)->file($path);
        if (\in_array($mime, ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'], true)) {
            throw new MediaException('Формат HEIC не поддерживается: сохраните фото в JPEG.');
        }
        $source = Imaging::INPUT[$mime] ?? throw new MediaException('Это не изображение или его формат не поддерживается.');

        $imaging = new Imaging($this->config);
        $info = $imaging->ping($path, $source);
        if ($info['width'] * $info['height'] > $this->config->int('media.max_megapixels') * 1000000) {
            throw new MediaException('Изображение больше ' . $this->config->int('media.max_megapixels') . ' мегапикселей.');
        }

        $clean = tempnam(sys_get_temp_dir(), 'es-img-');
        if ($clean === false) {
            throw new \RuntimeException('Cannot create a temporary file');
        }
        try {
            [$ext, $frames] = match ($source) {
                'JPEG' => [$this->jpeg($path, $clean, $info['orientation'], $info['quality'], $imaging), 1],
                'PNG' => [self::rewrite($path, $clean, Cleaner::png(...), 'png'), 1],
                'GIF' => [self::rewrite($path, $clean, Cleaner::gif(...), 'gif'), $info['frames']],
                default => [$this->convert($path, $clean, $source, $imaging), 1],
            };

            return $this->store($clean, $ext, $frames, $source);
        } finally {
            @unlink($clean);
        }
    }

    /**
     * @return string extension of the result
     */
    private function jpeg(string $path, string $clean, int $orientation, int $quality, Imaging $imaging): string
    {
        $jpegtran = $this->jpegtran();
        if ($jpegtran !== null) {
            $rotation = self::JPEGTRAN_ROTATION[$orientation] ?? [];
            // -perfect refuses a rotation that would leave partial blocks; then -trim drops the odd edge pixels
            foreach ($rotation === [] ? [[]] : [['-perfect'], ['-trim']] as $mode) {
                if ($this->run([$jpegtran, '-copy', 'icc', ...$mode, ...$rotation, '-outfile', $clean, $path])) {
                    return 'jpg';
                }
            }
            $this->logger->warning('jpegtran failed, cleaning the JPEG without it');
        }
        if (($orientation <= 1 || $orientation > 8)) {
            return self::rewrite($path, $clean, Cleaner::jpeg(...), 'jpg');
        }
        // A rotated photo without jpegtran: turn the pixels and encode again
        $image = $imaging->open($path, 'JPEG');
        $image->autoOrient();
        Imaging::stripKeepingProfile($image);
        Imaging::write($image, $clean, 'jpg', $quality);
        $image->clear();

        return 'jpg';
    }

    /**
     * @param callable(string): string $clean
     */
    private static function rewrite(string $path, string $target, callable $clean, string $ext): string
    {
        $data = file_get_contents($path);
        if ($data === false || file_put_contents($target, $clean($data)) === false) {
            throw new \RuntimeException('Cannot rewrite an uploaded image');
        }

        return $ext;
    }

    /**
     * Any other format: the first frame, on white, as JPEG of quality 95.
     */
    private function convert(string $path, string $clean, string $source, Imaging $imaging): string
    {
        $image = $imaging->open($path, $source);
        $image->setIteratorIndex(0);
        $image->autoOrient();
        if ($image->getImageAlphaChannel()) {
            $image->setImageBackgroundColor('white');
            $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        }
        Imaging::stripKeepingProfile($image);
        Imaging::write($image, $clean, 'jpg', 95);
        $image->clear();

        return 'jpg';
    }

    private function store(string $clean, string $ext, int $frames, string $source): Image
    {
        $sha = (string) hash_file('sha256', $clean);
        $row = $this->db->row('SELECT * FROM images WHERE sha256 = :sha', ['sha' => $sha]);
        if ($row !== null) {
            return Image::fromRow($row);
        }
        $size = @getimagesize($clean);
        if ($size === false) {
            throw new MediaException('После очистки файл не читается как изображение.');
        }
        $target = $this->store->originalPath($sha, $ext);
        $dir = \dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create {$dir}");
        }
        if (!copy($clean, $target)) {
            throw new \RuntimeException("Cannot write {$target}");
        }
        @chmod($target, 0o664);
        $image = new Image($sha, $ext, $size[0], $size[1], (int) filesize($target), $frames, $source);
        $this->db->run(
            'INSERT IGNORE INTO images (sha256, ext, width, height, bytes, frames, source_format, created_at)
             VALUES (:sha, :ext, :width, :height, :bytes, :frames, :source, :created)',
            [
                'sha' => $sha, 'ext' => $ext, 'width' => $image->width, 'height' => $image->height, 'bytes' => $image->bytes,
                'frames' => $frames, 'source' => $source, 'created' => Connection::now(),
            ],
        );

        return $image;
    }

    private function jpegtran(): ?string
    {
        $binary = $this->config->string('media.jpegtran');

        return $binary === '' ? null : Environment::findBinary($binary);
    }

    /**
     * Runs a command without a shell; true on exit code 0.
     *
     * @param list<string> $command
     */
    private function run(array $command): bool
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!\is_resource($process)) {
            return false;
        }
        stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0 && $error !== '') {
            $this->logger->warning('jpegtran: ' . trim($error));
        }

        return $code === 0;
    }
}
