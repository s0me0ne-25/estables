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

    /**
     * "12 марта 2025" in the site's time zone.
     */
    public static function longDate(\DateTimeInterface $date): string
    {
        $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
        $local = \DateTimeImmutable::createFromInterface($date)->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        return $local->format('j') . ' ' . $months[(int) $local->format('n') - 1] . ' ' . $local->format('Y');
    }

    /**
     * "1 996": thousands separated by a narrow no-break space, as in Russian typography.
     */
    public static function number(int $n): string
    {
        return number_format($n, 0, ',', "\u{202F}");
    }
}
