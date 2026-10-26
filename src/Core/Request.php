<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

/**
 * Immutable view of the incoming HTTP request.
 */
final readonly class Request
{
    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $post
     * @param array<string, string> $cookies
     * @param array<string, mixed>  $server
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $post = [],
        public array $cookies = [],
        public array $server = [],
    ) {}

    public static function fromGlobals(): self
    {
        $uri = \is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $method = \is_string($_SERVER['REQUEST_METHOD'] ?? null) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';

        /** @var array<string, string> $cookies */
        $cookies = array_filter($_COOKIE, 'is_string');

        return new self(
            $method,
            \is_string($path) && $path !== '' ? $path : '/',
            $_GET,
            $_POST,
            $cookies,
            $_SERVER,
        );
    }

    /**
     * A single-line string form field, trimmed; empty when absent.
     */
    public function input(string $name): string
    {
        $value = $this->post[$name] ?? '';

        return \is_string($value) ? trim($value) : '';
    }

    /**
     * A form field taken verbatim, for passwords.
     */
    public function rawInput(string $name): string
    {
        $value = $this->post[$name] ?? '';

        return \is_string($value) ? $value : '';
    }

    /**
     * Whether the form sent the field at all (a disabled field is not sent).
     */
    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->post);
    }

    /**
     * The password confirmation field, or null when the form left it out:
     * with the password shown in clear text the page disables that field.
     */
    public function passwordConfirmation(string $name = 'password_again'): ?string
    {
        return $this->has($name) ? $this->rawInput($name) : null;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isHttps(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        return \is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }

    public function ip(): string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? '';

        return \is_string($ip) ? $ip : '';
    }
}
