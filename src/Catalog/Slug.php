<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Catalog;

/**
 * Address segments: lowercase Latin letters, digits and single hyphens.
 */
final class Slug
{
    public const PATTERN = '~^[a-z0-9]+(?:-[a-z0-9]+)*$~';
    public const MAX_LENGTH = 100;

    /** Russian letters in Latin, close to what people type by hand. */
    private const CYRILLIC = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
        'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
        'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu',
        'я' => 'ya', 'і' => 'i', 'ї' => 'yi', 'є' => 'ye', 'ґ' => 'g',
    ];

    /**
     * Slug from a name: "Ленинградский завод" becomes "leningradskiy-zavod".
     * Returns an empty string when nothing usable is left.
     */
    public static function fromText(string $text): string
    {
        $text = strtr(mb_strtolower($text), self::CYRILLIC);
        // Other scripts and accented letters: Latin transcription, if intl can make one
        if (\function_exists('transliterator_transliterate') && preg_match('~[^\x00-\x7f]~', $text)) {
            $text = (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        }
        $slug = trim((string) preg_replace('~[^a-z0-9]+~', '-', $text), '-');
        if (\strlen($slug) > self::MAX_LENGTH) {
            $slug = rtrim(substr($slug, 0, self::MAX_LENGTH), '-');
        }

        return $slug;
    }

    public static function isValid(string $slug): bool
    {
        return \strlen($slug) <= self::MAX_LENGTH && preg_match(self::PATTERN, $slug) === 1;
    }
}
