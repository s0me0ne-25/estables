<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Auth;

/**
 * A rough "browser, system" label for the list of sessions. Good enough to
 * recognise one's own devices; not meant for statistics.
 */
final readonly class UserAgent
{
    private function __construct(
        public string $browser,
        public string $system,
        /** Bootstrap Icons name: phone, tablet, pc-display or laptop */
        public string $icon,
    ) {}

    public static function parse(string $ua): self
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'YaBrowser/') => 'Яндекс Браузер',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            $ua === '' => '',
            default => 'Другой браузер',
        };
        $system = match (true) {
            (bool) preg_match('~iPhone|iPad|iPod~', $ua) => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => '',
        };
        $icon = match (true) {
            str_contains($ua, 'iPad') || ($system === 'Android' && !str_contains($ua, 'Mobile')) => 'tablet',
            $system === 'iOS' || $system === 'Android' || str_contains($ua, 'Mobile') => 'phone',
            $system === 'Windows' => 'pc-display',
            default => 'laptop',
        };

        return new self($browser, $system, $icon);
    }

    public function label(): string
    {
        $label = implode(', ', array_filter([$this->browser, $this->system]));

        return $label !== '' ? $label : 'Неизвестное устройство';
    }
}
