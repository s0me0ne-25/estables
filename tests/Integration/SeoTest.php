<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Core\Kernel;
use Estables\Core\Logger;
use Estables\Core\Request;
use Estables\Core\Services;
use Estables\Tests\Browser;
use Estables\Tests\Fixtures\Images;

/**
 * What search engines and shared links see: robots.txt, the sitemap,
 * canonical addresses, Open Graph; and the security headers.
 */
final class SeoTest extends DatabaseTestCase
{
    private Services $services;
    private Browser $guest;
    private int $lfz;
    private int $hidden;

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = new Services($this->site->paths, $this->config, new Logger($this->site->paths->var() . '/log/estables.log'));
        $this->services->schema()->ensureCurrent();
        $this->services->users()->create('keeper', 'correct horse battery', '');
        $this->services->installState()->markInstalled();
        $rubrics = $this->services->rubrics();
        $this->lfz = $rubrics->create(['parent' => 0, 'name' => 'ЛФЗ', 'title' => '', 'slug' => 'lfz', 'description' => 'Фарфор Ленинграда.', 'visible' => true]);
        $this->hidden = $rubrics->create(['parent' => 0, 'name' => 'Черновики', 'title' => '', 'slug' => 'drafts', 'description' => '', 'visible' => false]);
        $this->item('ЛФЗ-1042', 'Конь вздыбленный', [$this->lfz], true);
        $this->item('ЛФЗ-1043', 'Голова скакуна', [$this->lfz], false);
        $this->item('X-1', 'Секрет', [$this->hidden], true);
        $this->guest = new Browser($this->site->paths);
    }

    public function testRobotsAndSitemapListOnlyWhatGuestsSee(): void
    {
        $robots = $this->guest->get('/robots.txt');
        self::assertSame('text/plain; charset=utf-8', $robots->header('Content-Type'));
        self::assertStringContainsString("Disallow: /admin/\n", $robots->body());
        self::assertStringContainsString("Sitemap: http://localhost/sitemap.xml\n", $robots->body(), 'Without app.base_url the request address is used');

        $index = $this->guest->get('/sitemap.xml');
        self::assertSame('application/xml; charset=utf-8', $index->header('Content-Type'));
        self::assertStringContainsString('<loc>http://localhost/sitemap-pages.xml</loc>', $index->body());
        self::assertStringContainsString('<loc>http://localhost/sitemap-items-1.xml</loc>', $index->body());
        self::assertStringNotContainsString('sitemap-items-2.xml', $index->body());
        self::assertNotFalse(simplexml_load_string($index->body()));

        $pages = $this->guest->get('/sitemap-pages.xml')->body();
        self::assertStringContainsString('<loc>http://localhost/catalog/lfz/</loc>', $pages);
        self::assertStringNotContainsString('drafts', $pages);

        $items = $this->guest->get('/sitemap-items-1.xml')->body();
        self::assertNotFalse(simplexml_load_string($items));
        self::assertStringContainsString('<loc>http://localhost/item/lfz-1042/</loc><lastmod>', $items);
        self::assertStringContainsString('<image:loc>http://localhost/media/originals/', $items);
        self::assertStringNotContainsString('lfz-1043', $items, 'Drafts are left out');
        self::assertStringNotContainsString('x-1', $items, 'Items of hidden rubrics are left out');
        self::assertSame(404, $this->guest->get('/sitemap-items-2.xml')->status());
    }

    public function testCanonicalAndOpenGraph(): void
    {
        $item = $this->guest->get('/item/lfz-1042/?utm_source=forum')->body();
        self::assertStringContainsString('<link rel="canonical" href="http://localhost/item/lfz-1042/">', $item);
        self::assertStringContainsString('<meta property="og:type" content="article">', $item);
        self::assertStringContainsString('<meta property="og:title" content="ЛФЗ-1042 Конь вздыбленный">', $item);
        self::assertMatchesRegularExpression('~<meta property="og:image" content="http://localhost/media/originals/[0-9a-f]{2}/[0-9a-f]{64}\.png">~', $item);
        self::assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $item);

        $rubric = $this->guest->get('/catalog/lfz/?sort=title&decade=1950')->body();
        self::assertStringContainsString('<link rel="canonical" href="http://localhost/catalog/lfz/">', $rubric);
        self::assertStringContainsString('<meta property="og:description" content="Фарфор Ленинграда.">', $rubric);
        self::assertStringContainsString('<meta property="og:image"', $rubric, 'The rubric borrows the cover of its item');

        $search = $this->guest->get('/search/?q=конь')->body();
        self::assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $search);
        self::assertStringNotContainsString('rel="canonical"', $search);
    }

    public function testSecurityHeaders(): void
    {
        $this->site->writeConfig(['app' => ['base_url' => 'https://estables.example.org'] + (array) $this->config->get('app')] + $this->configArray());
        $kernel = new Kernel($this->site->paths);
        $server = ['REMOTE_ADDR' => '203.0.113.24', 'HTTPS' => 'on', 'HTTP_HOST' => 'evil.example'];
        $https = $kernel->handle(new Request('GET', '/item/lfz-1042/', [], [], [], $server));
        self::assertSame('max-age=31536000', $https->header('Strict-Transport-Security'));
        self::assertSame('same-origin', $https->header('Cross-Origin-Opener-Policy'));
        self::assertSame('same-origin', $https->header('Cross-Origin-Resource-Policy'));
        self::assertSame('nosniff', $https->header('X-Content-Type-Options'));
        self::assertStringContainsString("frame-ancestors 'none'", (string) $https->header('Content-Security-Policy'));
        self::assertStringContainsString('href="https://estables.example.org/item/lfz-1042/"', $https->body(), 'app.base_url wins over the Host header');

        $http = $kernel->handle(new Request('GET', '/', [], [], [], ['REMOTE_ADDR' => '203.0.113.24']));
        self::assertNull($http->header('Strict-Transport-Security'), 'Never over plain HTTP');
    }

    /**
     * @param list<int> $rubrics
     */
    private function item(string $catno, string $name, array $rubrics, bool $published): void
    {
        $id = $this->services->items()->save(null, [
            'catno' => $catno, 'name' => $name, 'summary' => '', 'description' => '', 'yearFrom' => '1958', 'yearTo' => '',
            'published' => $published, 'rubrics' => $rubrics, 'main' => $rubrics[0], 'attributes' => [], 'variants' => [],
        ], null);
        if ($catno === 'ЛФЗ-1042' && \extension_loaded('imagick')) {
            @mkdir($this->site->paths->var() . '/fixtures', 0o777, true);
            $image = $this->services->ingest()->ingest(Images::png($this->site->paths->var() . '/fixtures/a.png'));
            $this->services->images()->attach($id, $image);
            $this->services->rubrics()->invalidate();
        }
    }
}
