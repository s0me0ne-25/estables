<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Auth\Captcha;

use Estables\Core\Config;
use Estables\Core\Logger;

/**
 * Builds the captcha chosen by captcha.provider in the configuration.
 */
final class CaptchaFactory
{
    public static function create(Config $config, Logger $logger): Captcha
    {
        return match ($config->string('captcha.provider')) {
            'turnstile' => new TurnstileCaptcha(
                $config->string('captcha.site_key'),
                $config->string('captcha.secret_key'),
                $logger,
            ),
            default => new StubCaptcha(),
        };
    }
}
