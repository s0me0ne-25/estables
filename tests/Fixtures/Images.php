<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Fixtures;

/**
 * Test pictures made on the fly, with the metadata real cameras and editors leave.
 */
final class Images
{
    /**
     * A JPEG with EXIF (orientation, a fake GPS note), an XMP packet and a comment.
     */
    public static function jpeg(string $path, int $width = 64, int $height = 32, int $orientation = 1, string $color = 'red'): string
    {
        $image = new \Imagick();
        $image->newImage($width, $height, $color);
        $image->setImageFormat('JPEG');
        $image->setImageCompressionQuality(90);
        $data = $image->getImageBlob();
        $image->clear();

        // TIFF header, one IFD entry: Orientation (0x0112), SHORT, 1, value
        $tiff = 'MM' . "\x00\x2A" . pack('N', 8) . pack('n', 1) . pack('nnNn', 0x0112, 3, 1, $orientation) . "\x00\x00" . pack('N', 0);
        $exif = "Exif\x00\x00" . $tiff . 'GPS 55.75N 37.61E';
        $xmp = "http://ns.adobe.com/xap/1.0/\x00<x:xmpmeta>secret author</x:xmpmeta>";
        $segments = self::segment(0xE1, $exif) . self::segment(0xE1, $xmp) . self::segment(0xFE, 'comment: secret');
        file_put_contents($path, substr($data, 0, 2) . $segments . substr($data, 2));

        return $path;
    }

    public static function png(string $path, int $width = 40, int $height = 20): string
    {
        $image = new \Imagick();
        $image->newImage($width, $height, 'blue');
        $image->setImageFormat('PNG');
        $data = $image->getImageBlob();
        $image->clear();
        $text = 'Author' . "\x00" . 'secret author';
        $chunk = pack('N', \strlen($text)) . 'tEXt' . $text . pack('N', crc32('tEXt' . $text));
        // After IHDR (8 + 25 bytes)
        file_put_contents($path, substr($data, 0, 33) . $chunk . substr($data, 33));

        return $path;
    }

    /**
     * An animated GIF of three frames with a comment and an XMP application block.
     */
    public static function gif(string $path, int $size = 600): string
    {
        $animation = new \Imagick();
        foreach (['red', 'green', 'blue'] as $color) {
            $frame = new \Imagick();
            $frame->newImage($size, $size / 2, $color);
            $frame->setImageFormat('GIF');
            $frame->setImageDelay(20);
            $animation->addImage($frame);
        }
        $animation->setImageIterations(0);
        $data = $animation->getImagesBlob();
        $animation->clear();
        $comment = "\x21\xFE" . "\x0F" . 'comment: secret' . "\x00";
        $xmp = "\x21\xFF\x0B" . 'XMP DataXMP' . "\x0C" . 'secret xmp!!' . "\x00";
        // After the header, the screen descriptor and the global colour table
        $flags = \ord($data[10]);
        $pos = 13 + (($flags & 0x80) !== 0 ? 3 * (2 << ($flags & 0x07)) : 0);
        file_put_contents($path, substr($data, 0, $pos) . $comment . $xmp . substr($data, $pos));

        return $path;
    }

    public static function webp(string $path, bool $alpha = true): string
    {
        $image = new \Imagick();
        $image->newImage(50, 30, $alpha ? 'transparent' : 'yellow');
        $image->setImageFormat('WEBP');
        $image->writeImage('WEBP:' . $path);
        $image->clear();

        return $path;
    }

    private static function segment(int $marker, string $payload): string
    {
        return "\xFF" . \chr($marker) . pack('n', \strlen($payload) + 2) . $payload;
    }
}
