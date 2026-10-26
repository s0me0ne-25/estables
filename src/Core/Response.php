<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

/**
 * HTTP response: status, headers and body.
 */
final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    /** @var list<string> Set-Cookie header values */
    private array $cookies = [];

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $body = '',
        private int $status = 200,
        array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function clearBody(): self
    {
        $this->body = '';

        return $this;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[strtolower($name)] = $value;

        return $this;
    }

    /**
     * Sets the header only if the response does not have it yet.
     */
    public function defaultHeader(string $name, string $value): self
    {
        if (!isset($this->headers[strtolower($name)])) {
            $this->setHeader($name, $value);
        }

        return $this;
    }

    /**
     * Adds a cookie. Without $maxAge it lives until the browser is closed.
     */
    public function setCookie(
        string $name,
        string $value,
        ?int $maxAge = null,
        bool $secure = true,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ): self {
        $cookie = $name . '=' . rawurlencode($value) . '; Path=/; SameSite=' . $sameSite;
        if ($maxAge !== null) {
            $cookie .= '; Max-Age=' . $maxAge;
        }
        if ($secure) {
            $cookie .= '; Secure';
        }
        if ($httpOnly) {
            $cookie .= '; HttpOnly';
        }
        $this->cookies[] = $cookie;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header(self::canonicalName($name) . ': ' . $value);
            }
            foreach ($this->cookies as $cookie) {
                header('Set-Cookie: ' . $cookie, false);
            }
        }
        echo $this->body;
    }

    private static function canonicalName(string $name): string
    {
        return implode('-', array_map('ucfirst', explode('-', $name)));
    }
}
