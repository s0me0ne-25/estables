<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

/**
 * Plain PHP templates. Inside a template $this is the View, so helpers are
 * called as $this->e(...), $this->asset(...), $this->url(...).
 * Every value printed into HTML goes through e(); raw HTML is printed only
 * from trusted, already rendered fragments ($content, partials).
 */
final class View
{
    /**
     * @param array<string, mixed> $globals variables available in every template
     */
    public function __construct(
        private readonly string $dir,
        private readonly string $publicDir,
        private readonly ?Router $router = null,
        private array $globals = [],
    ) {}

    public function share(string $name, mixed $value): void
    {
        $this->globals[$name] = $value;
    }

    /**
     * Renders a page template and wraps it into a layout.
     *
     * @param array<string, mixed> $vars
     */
    public function page(string $template, array $vars = [], string $layout = 'layouts/site'): string
    {
        $content = $this->partial($template, $vars);

        return $this->partial($layout, ['content' => $content] + $vars);
    }

    /**
     * Renders a template without a layout.
     *
     * @param array<string, mixed> $vars
     */
    public function partial(string $template, array $vars = []): string
    {
        $file = $this->dir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template {$template} not found");
        }
        $vars += $this->globals;
        $render = function (string $__file, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            require $__file;
        };
        ob_start();
        try {
            $render($file, $vars);
        } catch (\Throwable $e) {
            ob_end_clean();

            throw $e;
        }

        return (string) ob_get_clean();
    }

    /**
     * Escapes text for HTML content and attribute values.
     */
    public function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Date and time in the site's time zone, e.g. "26.10.2026, 09:14".
     */
    public function dateTime(?\DateTimeInterface $value, string $format = 'd.m.Y, H:i'): string
    {
        if ($value === null) {
            return '';
        }

        return \DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format($format);
    }

    /**
     * URL of a file under public/assets/ with a version for cache busting.
     */
    public function asset(string $path): string
    {
        $file = $this->publicDir . '/assets/' . $path;
        $version = is_file($file) ? (string) filemtime($file) : '0';

        return '/assets/' . $path . '?v=' . $version;
    }

    /**
     * @param array<string, string> $params
     */
    public function url(string $route, array $params = []): string
    {
        if ($this->router === null) {
            return '/';
        }

        return $this->router->url($route, $params);
    }
}
