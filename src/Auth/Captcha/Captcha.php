<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Auth\Captcha;

use Estables\Core\Request;

/**
 * A pluggable captcha on the login form: shows a widget, checks the answer.
 */
interface Captcha
{
    /**
     * Template under templates/captcha/ that renders the widget.
     */
    public function template(): string;

    /**
     * Variables for the widget template.
     *
     * @return array<string, mixed>
     */
    public function vars(): array;

    /**
     * Checks the answer submitted with the form.
     */
    public function verify(Request $request): bool;

    /**
     * Foreign sources the widget needs, by Content-Security-Policy directive.
     *
     * @return array<string, list<string>>
     */
    public function cspSources(): array;
}
