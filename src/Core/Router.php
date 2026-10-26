<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

/**
 * Maps request paths to handlers and builds URLs from route names.
 *
 * Patterns use placeholders: "/item/{slug}/" matches one path segment,
 * "/catalog/{path:.+}/" a custom regular expression. Canonical paths end
 * with a slash; a GET request for the same path without it is redirected.
 */
final class Router
{
    /** @var array<string, array{methods: list<string>, pattern: string, regex: string, handler: callable(Request, array<string, string>): Response}> */
    private array $routes = [];

    /**
     * @param string|list<string>                                 $methods
     * @param callable(Request, array<string, string>): Response $handler
     */
    public function add(string $name, string|array $methods, string $pattern, callable $handler): void
    {
        $this->routes[$name] = [
            'methods' => (array) $methods,
            'pattern' => $pattern,
            'regex' => self::compile($pattern),
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            $params = self::match($route['regex'], $request->path);
            if ($params === null) {
                continue;
            }
            $methods = $route['methods'];
            if (\in_array('GET', $methods, true)) {
                $methods[] = 'HEAD';
            }
            if (\in_array($request->method, $methods, true)) {
                return ($route['handler'])($request, $params);
            }
            array_push($allowed, ...$methods);
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'Method Not Allowed', ['Allow' => implode(', ', array_unique($allowed))]);
        }

        if ($request->method === 'GET' && !str_ends_with($request->path, '/') && $this->matches($request->path . '/')) {
            $query = $request->query === [] ? '' : '?' . http_build_query($request->query);

            return Response::redirect($request->path . '/' . $query, 301);
        }

        throw HttpException::notFound();
    }

    /**
     * Builds the path of a named route.
     *
     * @param array<string, string> $params
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->routes[$name])) {
            throw new \InvalidArgumentException("Unknown route {$name}");
        }

        return (string) preg_replace_callback(
            '~\{(\w+)(?::[^}]+)?\}~',
            static function (array $m) use ($params, $name): string {
                if (!isset($params[$m[1]])) {
                    throw new \InvalidArgumentException("Route {$name} needs parameter {$m[1]}");
                }

                return implode('/', array_map('rawurlencode', explode('/', $params[$m[1]])));
            },
            $this->routes[$name]['pattern'],
        );
    }

    private function matches(string $path): bool
    {
        foreach ($this->routes as $route) {
            if (self::match($route['regex'], $path) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>|null
     */
    private static function match(string $regex, string $path): ?array
    {
        if (!preg_match($regex, $path, $m)) {
            return null;
        }
        $params = [];
        foreach ($m as $key => $value) {
            if (\is_string($key)) {
                $params[$key] = rawurldecode($value);
            }
        }

        return $params;
    }

    private static function compile(string $pattern): string
    {
        $regex = '';
        $offset = 0;
        preg_match_all('~\{(\w+)(?::([^}]+))?\}~', $pattern, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($all as $m) {
            $regex .= preg_quote(substr($pattern, $offset, $m[0][1] - $offset), '~');
            $inner = isset($m[2]) && $m[2][1] >= 0 ? $m[2][0] : '[^/]+';
            $regex .= '(?P<' . $m[1][0] . '>' . $inner . ')';
            $offset = $m[0][1] + \strlen($m[0][0]);
        }
        $regex .= preg_quote(substr($pattern, $offset), '~');

        return '~^' . $regex . '$~u';
    }
}
