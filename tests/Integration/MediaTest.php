<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Core\Logger;
use Estables\Core\Services;
use Estables\Media\MediaException;
use Estables\Tests\Fixtures\Images;

/**
 * Upload processing and copies with real files.
 */
final class MediaTest extends DatabaseTestCase
{
    private Services $services;
    private string $dir;

    protected function setUp(): void
    {
        if (!\extension_loaded('imagick')) {
            self::markTestSkipped('imagick is not installed');
        }
        parent::setUp();
        $this->services = new Services($this->site->paths, $this->config, new Logger($this->site->paths->var() . '/log/estables.log'));
        $this->services->schema()->ensureCurrent();
        $this->dir = $this->site->paths->var() . '/fixtures';
        mkdir($this->dir, 0o777, true);
    }

    public function testJpegLosesMetadataAndTurnsByOrientation(): void
    {
        $image = $this->services->ingest()->ingest(Images::jpeg($this->dir . '/a.jpg', 64, 32, 6));
        self::assertSame('jpg', $image->ext);
        self::assertSame([32, 64], [$image->width, $image->height], 'Orientation 6 turns the picture');
        $data = (string) file_get_contents($this->services->media()->originalPath($image->sha, 'jpg'));
        self::assertStringNotContainsString('secret', $data);
        self::assertStringNotContainsString('GPS', $data);
        self::assertStringNotContainsString('Exif', $data);
        self::assertSame($image->sha, hash('sha256', $data));
    }

    public function testWithoutJpegtranMarkersAreDroppedInPhp(): void
    {
        $config = new \Estables\Core\Config(['media' => ['jpegtran' => '']] + $this->configArray());
        $services = new Services($this->site->paths, $config, new Logger($this->site->paths->var() . '/log/estables.log'));

        $plain = $services->ingest()->ingest(Images::jpeg($this->dir . '/a.jpg', 64, 32, 1));
        $data = (string) file_get_contents($services->media()->originalPath($plain->sha, 'jpg'));
        self::assertStringNotContainsString('secret', $data);
        self::assertSame([64, 32], [$plain->width, $plain->height]);

        $turned = $services->ingest()->ingest(Images::jpeg($this->dir . '/b.jpg', 64, 32, 8, 'green'));
        self::assertSame([32, 64], [$turned->width, $turned->height], 'Turned by re-encoding');
        self::assertStringNotContainsString('secret', (string) file_get_contents($services->media()->originalPath($turned->sha, 'jpg')));
    }

    public function testSamePictureWithOtherMetadataIsStoredOnce(): void
    {
        $first = $this->services->ingest()->ingest(Images::jpeg($this->dir . '/a.jpg'));
        $copy = Images::jpeg($this->dir . '/b.jpg');
        file_put_contents($copy, str_replace('secret', 'SECRET', (string) file_get_contents($copy)));
        $second = $this->services->ingest()->ingest($copy);
        self::assertSame($first->sha, $second->sha);
        self::assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM images'));
    }

    public function testPngAndGifKeepFormatWithoutMetadata(): void
    {
        $png = $this->services->ingest()->ingest(Images::png($this->dir . '/a.png'));
        self::assertSame(['png', 40, 20], [$png->ext, $png->width, $png->height]);
        self::assertStringNotContainsString('secret', (string) file_get_contents($this->services->media()->originalPath($png->sha, 'png')));

        $gif = $this->services->ingest()->ingest(Images::gif($this->dir . '/a.gif'));
        self::assertSame(['gif', 3], [$gif->ext, $gif->frames]);
        $data = (string) file_get_contents($this->services->media()->originalPath($gif->sha, 'gif'));
        self::assertStringNotContainsString('secret', $data);
        self::assertStringContainsString('NETSCAPE2.0', $data, 'Looping is kept');
    }

    public function testOtherFormatsBecomeJpegOnWhite(): void
    {
        $image = $this->services->ingest()->ingest(Images::webp($this->dir . '/a.webp'));
        self::assertSame(['jpg', 'WEBP'], [$image->ext, $image->sourceFormat]);
        $check = new \Imagick($this->services->media()->originalPath($image->sha, 'jpg'));
        $pixel = $check->getImagePixelColor(10, 10)->getColor();
        self::assertGreaterThan(250, $pixel['r'], 'Transparency became white');
        self::assertGreaterThanOrEqual(95, $check->getImageCompressionQuality());
    }

    public function testRejectsWhatIsNotAPicture(): void
    {
        file_put_contents($this->dir . '/x.jpg', '<?php echo "hi";');
        $this->expectException(MediaException::class);
        $this->services->ingest()->ingest($this->dir . '/x.jpg');
    }

    public function testRejectsSvgEvenWithAnImageName(): void
    {
        file_put_contents($this->dir . '/x.png', '<svg xmlns="http://www.w3.org/2000/svg"><image href="file:///etc/passwd"/></svg>');
        $this->expectException(MediaException::class);
        $this->services->ingest()->ingest($this->dir . '/x.png');
    }

    public function testCopiesAreSmallerAndKeepTheFormat(): void
    {
        $media = $this->services->media();
        $thumbnailer = $this->services->thumbnailer();
        $jpeg = $this->services->ingest()->ingest(Images::jpeg($this->dir . '/big.jpg', 1000, 500));
        $path = $thumbnailer->copy($jpeg, 400);
        self::assertSame([400, 200, IMAGETYPE_JPEG], \array_slice((array) getimagesize($path), 0, 3));

        $gif = $this->services->ingest()->ingest(Images::gif($this->dir . '/anim.gif', 600));
        $still = new \Imagick($thumbnailer->copy($gif, 400));
        self::assertSame(1, $still->getNumberImages(), 'The list preview is a still frame');
        $moving = new \Imagick($thumbnailer->copy($gif, 1600));
        self::assertSame(3, $moving->getNumberImages(), 'The large copy keeps the animation');

        self::assertStringStartsWith('/media/400/', $media->thumbUrl($gif));
        self::assertSame($media->originalUrl($gif), $media->largeUrl($gif), 'A small original is shown itself');
        $small = $this->services->ingest()->ingest(Images::png($this->dir . '/small.png'));
        self::assertSame($media->originalUrl($small), $media->thumbUrl($small));
    }
}
