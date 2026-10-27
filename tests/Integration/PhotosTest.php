<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Core\Logger;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Media\Image;
use Estables\Tests\Browser;
use Estables\Tests\Fixtures\Images;

/**
 * Photos of items and rubric covers: the editor, the item page, the cards.
 */
final class PhotosTest extends DatabaseTestCase
{
    private const PASSWORD = 'correct horse battery';

    private Services $services;
    private Browser $admin;
    private Browser $guest;
    private int $figures;
    private int $lfz;
    private string $fixtures;

    protected function setUp(): void
    {
        if (!\extension_loaded('imagick')) {
            self::markTestSkipped('imagick is not installed');
        }
        parent::setUp();
        $this->services = new Services($this->site->paths, $this->config, new Logger($this->site->paths->var() . '/log/estables.log'));
        $this->services->schema()->ensureCurrent();
        $this->services->users()->create('keeper', self::PASSWORD, '');
        $this->services->installState()->markInstalled();
        $rubrics = $this->services->rubrics();
        $this->figures = $rubrics->create(['parent' => 0, 'name' => 'Фигурки', 'title' => '', 'slug' => 'figurki', 'description' => '', 'visible' => true]);
        $this->lfz = $rubrics->create(['parent' => $this->figures, 'name' => 'ЛФЗ', 'title' => '', 'slug' => 'lfz', 'description' => '', 'visible' => true]);
        $this->guest = new Browser($this->site->paths);
        $this->admin = new Browser($this->site->paths);
        $form = $this->admin->get('/login/');
        $this->admin->post('/login/', ['_token' => Browser::field($form, '_token'), 'login' => 'keeper', 'password' => self::PASSWORD, 'captcha_confirm' => '1']);
        $this->fixtures = $this->site->paths->var() . '/fixtures';
        @mkdir($this->fixtures, 0o777, true);
    }

    public function testEditorUploadsArrangesAndRemovesPhotos(): void
    {
        $id = $this->item('ЛФЗ-1042', 'Конь вздыбленный');
        $editor = $this->admin->get("/admin/items/{$id}/");
        self::assertStringContainsString('data-es-upload="/admin/items/' . $id . '/photos/"', $editor->body());
        $empty = $this->guest->get('/item/lfz-1042/');
        self::assertStringContainsString('Фотографий пока нет', $empty->body());
        self::assertStringNotContainsString('glightbox', $empty->body());
        self::assertStringNotContainsString('style-src-attr', (string) $empty->header('Content-Security-Policy'));

        self::assertSame(403, $this->admin->upload("/admin/items/{$id}/photos/", [], ['photo' => Images::png($this->fixtures . '/x.png')])->status(), 'CSRF is checked');
        $first = $this->upload($id, Images::jpeg($this->fixtures . '/front.jpg', 3000, 2000));
        self::assertSame(200, $first->status(), $first->body());
        $tile = $this->json($first);
        self::assertTrue($tile['ok']);
        self::assertStringContainsString('name="photo[]"', (string) $tile['html']);
        $second = $this->upload($id, Images::png($this->fixtures . '/mark.png'));
        self::assertSame(200, $second->status());

        $again = $this->upload($id, $this->fixtures . '/front.jpg');
        self::assertSame(409, $again->status(), 'The same file is not added twice');
        file_put_contents($this->fixtures . '/notes.jpg', 'not a picture');
        $bad = $this->upload($id, $this->fixtures . '/notes.jpg');
        self::assertSame(422, $bad->status());
        self::assertFalse($this->json($bad)['ok']);

        $photos = $this->services->images()->photos($id);
        self::assertCount(2, $photos);
        [$front, $mark] = [$photos[0]['image'], $photos[1]['image']];
        self::assertSame(['jpg', 'png'], [$front->ext, $mark->ext]);

        // A form with errors keeps the posted order and captions.
        $invalid = $this->saveItem("/admin/items/{$id}/", ['catno' => 'ЛФЗ-1042', 'name' => '', 'photo' => [$mark->sha, $front->sha], 'photo_caption' => ['Клеймо', '']]);
        self::assertSame(422, $invalid->status());
        self::assertLessThan(strpos($invalid->body(), 'value="' . $front->sha . '"'), strpos($invalid->body(), 'value="' . $mark->sha . '"'));
        self::assertStringContainsString('value="Клеймо"', $invalid->body());
        self::assertSame($front->sha, $this->services->images()->photos($id)[0]['image']->sha, 'Nothing is saved yet');

        // The form puts the mark first and gives it a caption.
        $saved = $this->saveItem("/admin/items/{$id}/", [
            'catno' => 'ЛФЗ-1042', 'name' => 'Конь вздыбленный',
            'photo' => [$mark->sha, $front->sha], 'photo_caption' => ['Клеймо на постаменте', ''],
        ]);
        self::assertSame(303, $saved->status(), $saved->body());
        $photos = $this->services->images()->photos($id);
        self::assertSame([$mark->sha, $front->sha], array_map(static fn(array $p): string => $p['image']->sha, $photos));
        self::assertSame('Клеймо на постаменте', $photos[0]['caption']);

        $media = $this->services->media();
        $response = $this->guest->get('/item/lfz-1042/');
        self::assertStringContainsString("style-src-attr 'unsafe-inline'", (string) $response->header('Content-Security-Policy'), 'For the full-screen viewer');
        $page = $response->body();
        self::assertStringContainsString('glightbox.min.js', $page);
        self::assertStringContainsString('<img src="' . $media->largeUrl($mark) . '" alt="Клеймо на постаменте">', $page);
        self::assertStringContainsString('2 фото</span>', $page);
        self::assertStringContainsString('data-es-gallery-thumbs', $page);
        self::assertStringContainsString('href="/download/' . $mark->sha . '/lfz-1042-1.png"', $page);
        self::assertStringContainsString('data-original="/download/' . $front->sha . '/lfz-1042-2.jpg"', $page);
        self::assertStringContainsString('alt="Конь вздыбленный, фото 2"', $page);
        self::assertStringContainsString($media->copyUrl(1600, $front), $page, 'A big original is shown through the large copy');
        self::assertStringContainsString('<span class="es-meta" data-es-gallery-info>' . $mark->label() . '</span>', $page);

        // Removing a photo from the form unlinks it; the file waits for gc.
        $this->saveItem("/admin/items/{$id}/", ['catno' => 'ЛФЗ-1042', 'name' => 'Конь вздыбленный', 'photo' => [$front->sha], 'photo_caption' => ['']]);
        self::assertCount(1, $this->services->images()->photos($id));
        self::assertTrue($this->services->images()->exists($mark->sha));
        $single = $this->guest->get('/item/lfz-1042/')->body();
        self::assertStringNotContainsString('data-es-gallery-thumbs', $single, 'One photo needs no thumbnails');
        self::assertStringContainsString('alt="Конь вздыбленный"', $single);
    }

    public function testCardsAndTilesShowCovers(): void
    {
        $id = $this->item('ЛФЗ-1042', 'Конь вздыбленный');
        $listing = $this->guest->get('/catalog/figurki/lfz/')->body();
        self::assertStringContainsString('es-mount-empty', $listing);
        self::assertStringNotContainsString('glightbox', $listing, 'The viewer is loaded on item pages only');

        self::assertSame(200, $this->upload($id, Images::jpeg($this->fixtures . '/front.jpg', 1200, 900))->status());
        $front = $this->services->images()->photos($id)[0]['image'];
        $media = $this->services->media();

        $listing = $this->guest->get('/catalog/figurki/lfz/')->body();
        self::assertStringContainsString('<img src="' . $media->thumbUrl($front) . '" srcset="' . $media->thumbUrl($front) . ' 400w, ' . $media->originalUrl($front) . ' 1200w"', $listing);
        self::assertStringContainsString('alt="Конь вздыбленный" loading="lazy"', $listing);

        // A rubric without its own cover takes the first photo of its first item, then of a subrubric.
        $root = $this->guest->get('/')->body();
        self::assertStringContainsString('<img src="' . $media->thumbUrl($front) . '" alt="" loading="lazy">', $root, 'Фигурки borrow from ЛФЗ');
        $admin = $this->admin->get('/admin/')->body();
        self::assertStringContainsString($media->thumbUrl($front), $admin);

        // Its own cover wins and can be removed again.
        $page = $this->admin->get('/admin/rubrics/');
        $cover = Images::png($this->fixtures . '/cover.png', 300, 200);
        $saved = $this->admin->upload('/admin/rubrics/', [
            '_csrf' => Browser::field($page, '_csrf'), 'action' => 'save', 'id' => (string) $this->figures, 'parent' => '0',
            'name' => 'Фигурки', 'title' => '', 'slug' => 'figurki', 'description' => '', 'visible' => '1',
        ], ['cover' => $cover]);
        self::assertSame(303, $saved->status(), $saved->body());
        $own = $this->services->rubrics()->find($this->figures)?->cover;
        self::assertNotNull($own);
        $image = $this->services->images()->find($own);
        self::assertInstanceOf(Image::class, $image);
        self::assertStringContainsString('<img src="' . $media->originalUrl($image) . '" alt="" loading="lazy">', $this->guest->get('/')->body(), 'A small original is its own thumbnail');

        $page = $this->admin->get('/admin/rubrics/');
        $this->admin->post('/admin/rubrics/', [
            '_csrf' => Browser::field($page, '_csrf'), 'action' => 'save', 'id' => (string) $this->figures, 'parent' => '0',
            'name' => 'Фигурки', 'title' => '', 'slug' => 'figurki', 'description' => '', 'visible' => '1', 'remove_cover' => '1',
        ]);
        self::assertNull($this->services->rubrics()->find($this->figures)?->cover);
        self::assertStringContainsString($media->thumbUrl($front), $this->guest->get('/')->body());
    }

    public function testDraftPhotosDoNotBecomeRubricCovers(): void
    {
        $id = $this->item('ЛФЗ-1042', 'Конь вздыбленный', ['status' => 'draft']);
        self::assertSame(200, $this->upload($id, Images::png($this->fixtures . '/a.png'))->status());
        $tree = $this->services->rubrics()->tree();
        self::assertNull($tree->node($this->lfz)['cover']);
        self::assertNull($tree->node($this->figures)['cover']);
    }

    private function upload(int $item, string $path): Response
    {
        $editor = $this->admin->get("/admin/items/{$item}/");

        return $this->admin->upload("/admin/items/{$item}/photos/", ['_csrf' => Browser::field($editor, '_csrf')], ['photo' => $path]);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        $data = json_decode($response->body(), true);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param array<string, string|list<string>> $fields
     */
    private function saveItem(string $url, array $fields): Response
    {
        $form = $this->admin->get($url);
        /** @var array<string, string> $post */
        $post = $fields + [
            '_csrf' => Browser::field($form, '_csrf'), 'action' => 'save', 'catno' => '', 'name' => '', 'summary' => '', 'description' => '',
            'year_from' => '', 'year_to' => '', 'status' => 'published', 'rubrics' => [(string) $this->lfz], 'main_rubric' => (string) $this->lfz,
        ];

        return $this->admin->post($url, $post);
    }

    /**
     * @param array<string, string|list<string>> $extra
     */
    private function item(string $catno, string $name, array $extra = []): int
    {
        $response = $this->saveItem('/admin/items/new/', ['catno' => $catno, 'name' => $name] + $extra);
        self::assertSame(303, $response->status(), $response->body());
        if (!preg_match('~/admin/items/(\d+)/~', (string) $response->header('Location'), $m)) {
            self::fail('No item address after saving');
        }

        return (int) $m[1];
    }
}
