<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Cli\Application;
use Estables\Core\Logger;
use Estables\Core\Services;
use Estables\Tests\Browser;
use Estables\Tests\Fixtures\Images;

/**
 * Copies made on request, downloads and the garbage collector.
 */
final class MediaRoutesTest extends DatabaseTestCase
{
    private Services $services;

    protected function setUp(): void
    {
        if (!\extension_loaded('imagick')) {
            self::markTestSkipped('imagick is not installed');
        }
        parent::setUp();
        $this->services = new Services($this->site->paths, $this->config, new Logger($this->site->paths->var() . '/log/estables.log'));
        $this->services->schema()->ensureCurrent();
        $this->services->users()->create('keeper', 'correct horse battery', '');
        $this->services->installState()->markInstalled();
        @mkdir($this->site->paths->var() . '/fixtures', 0o777, true);
    }


    public function testCopyIsMadeOnRequestAndDownloadGoesThroughNginx(): void
    {
        $image = $this->services->ingest()->ingest(Images::jpeg($this->site->paths->var() . '/fixtures/big.jpg', 900, 600));
        $browser = new Browser($this->site->paths);
        $url = $this->services->media()->copyUrl(400, $image);

        $copy = $browser->get($url);
        self::assertSame(200, $copy->status());
        self::assertSame('image/jpeg', $copy->header('Content-Type'));
        self::assertStringContainsString('immutable', (string) $copy->header('Cache-Control'));
        self::assertFileExists($this->services->media()->copyPath(400, $image->sha, 'jpg'));

        self::assertSame(404, $browser->get('/media/123/' . substr($image->sha, 0, 2) . '/' . $image->sha . '.jpg')->status(), 'Only configured sizes');
        self::assertSame(404, $browser->get('/media/400/00/' . $image->sha . '.jpg')->status());
        self::assertSame(404, $browser->get('/media/400/' . substr($image->sha, 0, 2) . '/' . $image->sha . '.png')->status());

        $download = $browser->get('/download/' . $image->sha . '/lfz-1042-1.jpg');
        self::assertSame($this->services->media()->originalUrl($image), $download->header('X-Accel-Redirect'));
        self::assertSame('attachment; filename="lfz-1042-1.jpg"', $download->header('Content-Disposition'));
        self::assertSame(404, $browser->get('/download/' . $image->sha . '/x.png')->status());
        self::assertSame(404, $browser->get('/download/' . str_repeat('0', 64) . '/x.jpg')->status());
    }

    public function testGcDeletesUnusedImagesAfterTheDelay(): void
    {
        $image = $this->services->ingest()->ingest(Images::png($this->site->paths->var() . '/fixtures/a.png'));
        $path = $this->services->media()->originalPath($image->sha, 'png');
        self::assertSame(0, $this->gc()['code']);
        self::assertFileExists($path, 'Too young to be collected');

        $this->db->run('UPDATE images SET created_at = UTC_TIMESTAMP() - INTERVAL 2 DAY');
        $result = $this->gc();
        self::assertStringContainsString('изображений 1', $result['out']);
        self::assertFileDoesNotExist($path);
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM images'));
    }

    /**
     * @return array{code: int, out: string}
     */
    private function gc(): array
    {
        $out = fopen('php://memory', 'r+');
        self::assertIsResource($out);
        $code = new Application($this->site->paths, null, $out, $out)->run(['gc']);
        rewind($out);

        return ['code' => $code, 'out' => (string) stream_get_contents($out)];
    }
}
