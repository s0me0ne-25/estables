<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

/**
 * Russian text helpers.
 */
final class Text
{
    /**
     * Number with the matching noun form: plural(5, 'минута', 'минуты', 'минут') is "5 минут".
     */
    public static function plural(int $n, string $one, string $few, string $many): string
    {
        $mod10 = abs($n) % 10;
        $mod100 = abs($n) % 100;
        $word = match (true) {
            $mod10 === 1 && $mod100 !== 11 => $one,
            $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => $few,
            default => $many,
        };

        return "{$n} {$word}";
    }
}
