<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Media;

/**
 * Removes metadata from image files without touching the pixels: the file
 * structure is parsed and the blocks that carry metadata are dropped.
 *
 * - JPEG (fallback when jpegtran is missing): APP1 (EXIF, XMP), APP3–APP13
 *   (IPTC and others), APP15 and comments go; JFIF (APP0), the ICC profile
 *   (APP2) and the Adobe colour marker (APP14) stay.
 * - PNG: text chunks, eXIf and tIME go.
 * - GIF: comments and application extensions go, except the ones that
 *   control looping of an animation (NETSCAPE2.0, ANIMEXTS1.0).
 */
final class Cleaner
{
    private const PNG_DROP = ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'];
    private const GIF_KEEP_APPS = ['NETSCAPE2.0', 'ANIMEXTS1.0'];

    public static function jpeg(string $data): string
    {
        if (!str_starts_with($data, "\xFF\xD8")) {
            throw new MediaException('Файл JPEG повреждён.');
        }
        $out = "\xFF\xD8";
        $pos = 2;
        $length = \strlen($data);
        while ($pos + 4 <= $length) {
            if ($data[$pos] !== "\xFF") {
                throw new MediaException('Файл JPEG повреждён.');
            }
            $marker = \ord($data[$pos + 1]);
            if ($marker === 0xFF) {
                $pos++;
                continue;
            }
            if ($marker === 0xDA) {
                // Start of scan: the compressed image follows to the end
                return $out . substr($data, $pos);
            }
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $out .= substr($data, $pos, 2);
                $pos += 2;
                continue;
            }
            $size = unpack('n', $data, $pos + 2)[1] ?? 0;
            $segment = substr($data, $pos, $size + 2);
            $keep = match (true) {
                $marker === 0xE0, $marker === 0xEE => true,
                $marker === 0xE2 => str_starts_with(substr($segment, 4), "ICC_PROFILE\0"),
                $marker >= 0xE1 && $marker <= 0xEF, $marker === 0xFE => false,
                default => true,
            };
            if ($keep) {
                $out .= $segment;
            }
            $pos += $size + 2;
        }

        throw new MediaException('Файл JPEG повреждён.');
    }

    public static function png(string $data): string
    {
        if (!str_starts_with($data, "\x89PNG\r\n\x1A\n")) {
            throw new MediaException('Файл PNG повреждён.');
        }
        $out = substr($data, 0, 8);
        $pos = 8;
        $length = \strlen($data);
        while ($pos + 12 <= $length) {
            $size = unpack('N', $data, $pos)[1] ?? 0;
            $type = substr($data, $pos + 4, 4);
            $chunk = substr($data, $pos, $size + 12);
            if (\strlen($chunk) !== $size + 12) {
                throw new MediaException('Файл PNG повреждён.');
            }
            if (!\in_array($type, self::PNG_DROP, true)) {
                $out .= $chunk;
            }
            $pos += $size + 12;
            if ($type === 'IEND') {
                return $out;
            }
        }

        throw new MediaException('Файл PNG повреждён.');
    }

    public static function gif(string $data): string
    {
        if (!preg_match('~^GIF8[79]a~', $data) || \strlen($data) < 13) {
            throw new MediaException('Файл GIF повреждён.');
        }
        $flags = \ord($data[10]);
        $pos = 13 + (($flags & 0x80) !== 0 ? 3 * (2 << ($flags & 0x07)) : 0);
        $out = substr($data, 0, $pos);
        $length = \strlen($data);
        while ($pos < $length) {
            $byte = $data[$pos];
            if ($byte === "\x3B") {
                return $out . "\x3B";
            }
            if ($byte === "\x2C") {
                // Image: descriptor, local colour table, LZW size, data sub-blocks
                $descriptorFlags = \ord($data[$pos + 9] ?? "\0");
                $end = $pos + 10 + (($descriptorFlags & 0x80) !== 0 ? 3 * (2 << ($descriptorFlags & 0x07)) : 0) + 1;
                $end = self::skipSubBlocks($data, $end);
                $out .= substr($data, $pos, $end - $pos);
                $pos = $end;
                continue;
            }
            if ($byte === "\x21") {
                $label = \ord($data[$pos + 1] ?? "\0");
                $end = self::skipSubBlocks($data, $pos + 2);
                $keep = match ($label) {
                    0xFE => false,
                    0xFF => \in_array(substr($data, $pos + 3, 11), self::GIF_KEEP_APPS, true),
                    default => true,
                };
                if ($keep) {
                    $out .= substr($data, $pos, $end - $pos);
                }
                $pos = $end;
                continue;
            }

            throw new MediaException('Файл GIF повреждён.');
        }

        // No trailer: close the file properly
        return $out . "\x3B";
    }

    /**
     * Position after a chain of data sub-blocks that starts at $pos.
     */
    private static function skipSubBlocks(string $data, int $pos): int
    {
        $length = \strlen($data);
        while ($pos < $length) {
            $size = \ord($data[$pos]);
            $pos += 1 + $size;
            if ($size === 0) {
                return $pos;
            }
        }
        throw new MediaException('Файл GIF повреждён.');
    }
}
