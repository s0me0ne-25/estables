<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests;

use Estables\Core\Kernel;
use Estables\Core\Paths;
use Estables\Core\Request;
use Estables\Core\Response;

/**
 * Drives the kernel like a browser: one Kernel per request, with a cookie jar.
 */
final class Browser
{
    /** @var array<string, string> */
    public array $cookies = [];

    /** @var array<string, string> raw Set-Cookie values of the last response, by name */
    public array $lastSetCookies = [];

    public function __construct(
        private readonly Paths $paths,
        private readonly string $userAgent = 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0',
    ) {}

    public function get(string $url): Response
    {
        return $this->request('GET', $url);
    }

    /**
     * @param array<string, string|list<string>> $fields
     */
    public function post(string $url, array $fields): Response
    {
        return $this->request('POST', $url, $fields);
    }

    /**
     * A multipart form: files are local paths sent under the given field names.
     *
     * @param array<string, string|list<string>> $fields
     * @param array<string, string> $files  field name => path of the file to send
     */
    public function upload(string $url, array $fields, array $files): Response
    {
        $sent = [];
        foreach ($files as $field => $path) {
            $size = filesize($path);
            $sent[$field] = ['name' => basename($path), 'tmp' => $path, 'error' => UPLOAD_ERR_OK, 'size' => $size === false ? 0 : $size];
        }

        return $this->request('POST', $url, $fields, $sent);
    }

    /**
     * @param array<string, string|list<string>>                                $fields
     * @param array<string, array{name: string, tmp: string, error: int, size: int}> $files
     */
    public function request(string $method, string $url, array $fields = [], array $files = []): Response
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        /** @var array<string, mixed> $query */
        $response = new Kernel($this->paths)->handle(new Request($method, $path, $query, $fields, $this->cookies, [
            'REMOTE_ADDR' => '203.0.113.24',
            'HTTP_USER_AGENT' => $this->userAgent,
        ], $files));
        $this->lastSetCookies = [];
        foreach ($response->cookies() as $cookie) {
            [$pair] = explode(';', $cookie, 2);
            [$name, $value] = explode('=', $pair, 2);
            $this->lastSetCookies[$name] = $cookie;
            if (str_contains($cookie, 'Max-Age=0')) {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = rawurldecode($value);
            }
        }

        return $response;
    }

    /**
     * Value of a hidden form field in the page, by name; the first match.
     */
    public static function field(Response $response, string $name): string
    {
        if (!preg_match('~name="' . preg_quote($name, '~') . '" value="([^"]*)"~', $response->body(), $m)) {
            throw new \RuntimeException("No field {$name} in the page");
        }

        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
    }
}
