<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Auth\Captcha;

use Estables\Core\Logger;
use Estables\Core\Request;

/**
 * Cloudflare Turnstile: https://developers.cloudflare.com/turnstile/
 *
 * The widget posts its answer in the "cf-turnstile-response" field; the
 * server confirms it with the siteverify API using the secret key.
 */
final class TurnstileCaptcha implements Captcha
{
    public const ORIGIN = 'https://challenges.cloudflare.com';
    public const FIELD = 'cf-turnstile-response';
    private const VERIFY_URL = self::ORIGIN . '/turnstile/v0/siteverify';

    /** @var \Closure(string $url, array<string, string> $fields): ?string */
    private readonly \Closure $transport;

    /**
     * @param (\Closure(string, array<string, string>): ?string)|null $transport HTTP POST for tests; returns the body or null
     */
    public function __construct(
        private readonly string $siteKey,
        private readonly string $secretKey,
        private readonly Logger $logger,
        ?\Closure $transport = null,
    ) {
        $this->transport = $transport ?? self::post(...);
    }

    public function template(): string
    {
        return 'captcha/turnstile';
    }

    public function vars(): array
    {
        return ['siteKey' => $this->siteKey, 'scriptUrl' => self::ORIGIN . '/turnstile/v0/api.js'];
    }

    public function verify(Request $request): bool
    {
        $answer = $request->input(self::FIELD);
        if ($answer === '' || \strlen($answer) > 2048) {
            return false;
        }
        $body = ($this->transport)(self::VERIFY_URL, array_filter([
            'secret' => $this->secretKey,
            'response' => $answer,
            'remoteip' => $request->ip(),
        ], static fn(string $value): bool => $value !== ''));
        if ($body === null) {
            $this->logger->warning('Turnstile siteverify is unreachable');

            return false;
        }
        $result = json_decode($body, true);
        if (!\is_array($result) || ($result['success'] ?? false) !== true) {
            $codes = \is_array($result) && \is_array($result['error-codes'] ?? null) ? implode(', ', array_filter($result['error-codes'], 'is_string')) : 'bad response';
            if (str_contains($codes, 'secret')) {
                $this->logger->warning("Turnstile rejected the secret key: {$codes}");
            }

            return false;
        }

        return true;
    }

    public function cspSources(): array
    {
        return ['script-src' => [self::ORIGIN], 'frame-src' => [self::ORIGIN]];
    }

    /**
     * @param array<string, string> $fields
     */
    private static function post(string $url, array $fields): ?string
    {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($fields),
            'timeout' => 5,
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);

        return $body === false ? null : $body;
    }
}
