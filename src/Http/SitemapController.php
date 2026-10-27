<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Http;

use Estables\Core\HttpException;
use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Core\Services;

/**
 * robots.txt and the sitemap for search engines: an index, a file with the
 * site pages and rubrics, and files of items, ITEMS_PER_FILE each (the
 * format allows 50 000 addresses per file). Only what guests can see.
 */
final class SitemapController
{
    public const ITEMS_PER_FILE = 10000;

    private const CACHE = 'public, max-age=3600';

    public function __construct(
        private readonly Services $services,
        private readonly string $siteUrl,
    ) {}

    /**
     * @param array<string, string> $params
     */
    public function robots(Request $request, array $params): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin/',
            'Disallow: /login/',
            'Disallow: /search/',
            'Disallow: /download/',
            '',
            'Sitemap: ' . $this->siteUrl . '/sitemap.xml',
        ];

        return new Response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => self::CACHE,
        ]);
    }

    /**
     * /sitemap.xml: the list of sitemap files.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): Response
    {
        $files = ['/sitemap-pages.xml'];
        $count = $this->services->items()->publicCount($this->shownRubrics());
        for ($n = 1; ($n - 1) * self::ITEMS_PER_FILE < $count; $n++) {
            $files[] = "/sitemap-items-{$n}.xml";
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($files as $file) {
            $xml .= '  <sitemap><loc>' . $this->e($this->siteUrl . $file) . "</loc></sitemap>\n";
        }

        return self::xml($xml . "</sitemapindex>\n");
    }

    /**
     * /sitemap-pages.xml: the catalogue root, "О справочнике" and every shown rubric.
     *
     * @param array<string, string> $params
     */
    public function pages(Request $request, array $params): Response
    {
        $tree = $this->services->rubrics()->tree();
        $urls = ['/', '/about/'];
        foreach ($this->shownRubrics() as $id) {
            $urls[] = $tree->url($id);
        }
        $xml = self::open(false);
        foreach ($urls as $url) {
            $xml .= '  <url><loc>' . $this->e($this->siteUrl . $url) . "</loc></url>\n";
        }

        return self::xml($xml . "</urlset>\n");
    }

    /**
     * /sitemap-items-<n>.xml: items with the date of their last change and their cover.
     *
     * @param array<string, string> $params
     */
    public function items(Request $request, array $params): Response
    {
        $n = (int) $params['n'];
        $items = $n < 1 ? [] : $this->services->items()->publicSlice($this->shownRubrics(), ($n - 1) * self::ITEMS_PER_FILE, self::ITEMS_PER_FILE);
        if ($items === []) {
            throw HttpException::notFound();
        }
        $media = $this->services->media();
        $xml = self::open(true);
        foreach ($items as $item) {
            $xml .= '  <url><loc>' . $this->e($this->siteUrl . '/item/' . $item['slug'] . '/') . '</loc>'
                . '<lastmod>' . $item['updatedAt']->format('Y-m-d\TH:i:s\Z') . '</lastmod>';
            if ($item['cover'] !== null) {
                $xml .= '<image:image><image:loc>' . $this->e($this->siteUrl . $media->largeUrl($item['cover'])) . '</image:loc></image:image>';
            }
            $xml .= "</url>\n";
        }

        return self::xml($xml . "</urlset>\n");
    }

    /**
     * Rubrics guests can see, in tree order.
     *
     * @return list<int>
     */
    private function shownRubrics(): array
    {
        $tree = $this->services->rubrics()->tree();

        return array_values(array_filter(array_column($tree->flatten(), 'id'), $tree->isShown(...)));
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function open(bool $images): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ($images ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '') . ">\n";
    }

    private static function xml(string $body): Response
    {
        return new Response($body, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => self::CACHE,
        ]);
    }
}
