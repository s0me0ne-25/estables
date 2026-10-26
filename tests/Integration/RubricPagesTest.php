<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Core\Logger;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Tests\Browser;

/**
 * Rubricator in the admin area and on the public site, request by request.
 */
final class RubricPagesTest extends DatabaseTestCase
{
    private const PASSWORD = 'correct horse battery';

    private Services $services;
    private Browser $admin;
    private Browser $guest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = new Services($this->site->paths, $this->config, new Logger($this->site->paths->var() . '/log/estables.log'));
        $this->services->schema()->ensureCurrent();
        $this->services->users()->create('keeper', self::PASSWORD, '');
        $this->services->installState()->markInstalled();
        $this->guest = new Browser($this->site->paths);
        $this->admin = new Browser($this->site->paths);
        $form = $this->admin->get('/login/');
        $this->admin->post('/login/', ['_token' => Browser::field($form, '_token'), 'login' => 'keeper', 'password' => self::PASSWORD, 'captcha_confirm' => '1']);
    }

    public function testEmptyRubricatorOffersTheForm(): void
    {
        $page = $this->admin->get('/admin/rubrics/');
        self::assertSame(200, $page->status());
        self::assertStringContainsString('Разделов пока нет', $page->body());
        self::assertStringContainsString('Новый раздел', $page->body());
        self::assertStringContainsString('Справочник пока пуст', $this->guest->get('/')->body());
    }

    public function testCreateEditAndRedirect(): void
    {
        $invalid = $this->save(['name' => '', 'slug' => 'Плохой']);
        self::assertSame(422, $invalid->status());
        self::assertStringContainsString('Укажите название.', $invalid->body());

        $created = $this->save(['name' => 'Фигурки', 'description' => "Первый абзац.\n\nВторой."]);
        self::assertSame(303, $created->status());
        $id = $this->id('figurki');
        self::assertSame("/admin/rubrics/?id={$id}&done=created", $created->header('Location'));
        $child = $this->save(['name' => 'Фарфор', 'parent' => (string) $id]);
        self::assertSame(303, $child->status());

        // Public pages
        $root = $this->guest->get('/');
        self::assertStringContainsString('href="/catalog/figurki/">Фигурки</a></h3>', $root->body());
        $page = $this->guest->get('/catalog/figurki/');
        self::assertSame(200, $page->status());
        self::assertStringContainsString('<p class="es-lead">Первый абзац.</p>', $page->body());
        self::assertStringContainsString('<p class="es-lead mb-0">Второй.</p>', $page->body());
        self::assertStringContainsString('<li class="is-current">', $page->body());
        self::assertStringContainsString('href="/catalog/figurki/farfor/">Фарфор</a></h3>', $page->body());
        $leaf = $this->guest->get('/catalog/figurki/farfor/');
        self::assertStringContainsString('<li class="breadcrumb-item"><a href="/catalog/figurki/">Фигурки</a></li>', $leaf->body());
        self::assertSame('/catalog/figurki/farfor/', $this->guest->get('/catalog/figurki/farfor')->header('Location'));
        self::assertSame('/', $this->guest->get('/catalog/')->header('Location'));
        self::assertSame(404, $this->guest->get('/catalog/nothing/')->status());

        // New address: the old one redirects, subtree included
        self::assertSame(303, $this->save(['id' => (string) $id, 'name' => 'Фигурки', 'slug' => 'figury'])->status());
        $redirect = $this->guest->get('/catalog/figurki/farfor/');
        self::assertSame(301, $redirect->status());
        self::assertSame('/catalog/figury/farfor/', $redirect->header('Location'));
    }

    public function testHiddenRubricIsForAdministratorsOnly(): void
    {
        $this->save(['name' => 'Тайное', 'visible' => '']);
        $this->save(['name' => 'Внутри', 'parent' => (string) $this->id('taynoe')]);

        self::assertSame(404, $this->guest->get('/catalog/taynoe/')->status());
        self::assertSame(404, $this->guest->get('/catalog/taynoe/vnutri/')->status(), 'The subtree is hidden too');
        self::assertStringNotContainsString('Тайное', $this->guest->get('/')->body());

        $page = $this->admin->get('/catalog/taynoe/vnutri/');
        self::assertSame(200, $page->status());
        self::assertStringContainsString('Раздел скрыт на сайте', $page->body());
        self::assertStringContainsString('noindex', $page->body());
        self::assertStringContainsString('es-hidden-mark', $this->admin->get('/')->body());
    }

    public function testMoveEndpoint(): void
    {
        $this->save(['name' => 'Альфа']);
        $this->save(['name' => 'Бета']);
        $alpha = $this->id('alfa');
        $beta = $this->id('beta');

        $page = $this->admin->get('/admin/rubrics/');
        self::assertSame(403, $this->admin->post('/admin/rubrics/move/', ['id' => (string) $beta, 'parent' => '0', 'index' => '0'])->status());

        $csrf = Browser::field($page, '_csrf');
        $ok = $this->admin->post('/admin/rubrics/move/', ['_csrf' => $csrf, 'id' => (string) $beta, 'parent' => (string) $alpha, 'index' => '0']);
        self::assertSame(200, $ok->status());
        self::assertSame('{"ok":true}', $ok->body());
        self::assertSame(301, $this->guest->get('/catalog/beta/')->status());
        self::assertSame(200, $this->guest->get('/catalog/alfa/beta/')->status());

        $refused = $this->admin->post('/admin/rubrics/move/', ['_csrf' => $csrf, 'id' => (string) $alpha, 'parent' => (string) $beta, 'index' => '0']);
        self::assertSame(409, $refused->status());
        self::assertStringContainsString('"ok":false', $refused->body());
    }

    public function testDeleteRefusesSubrubrics(): void
    {
        $this->save(['name' => 'Альфа']);
        $alpha = $this->id('alfa');
        $this->save(['name' => 'Бета', 'parent' => (string) $alpha]);

        $refused = $this->post(['action' => 'delete', 'id' => (string) $alpha]);
        self::assertSame(422, $refused->status());
        self::assertStringContainsString('Сначала перенесите или удалите подразделы', $refused->body());

        $deleted = $this->post(['action' => 'delete', 'id' => (string) $this->id('beta')]);
        self::assertSame("/admin/rubrics/?id={$alpha}&done=deleted", $deleted->header('Location'));
        self::assertSame(404, $this->guest->get('/catalog/alfa/beta/')->status());
    }

    /**
     * @param array<string, string> $fields
     */
    private function save(array $fields): Response
    {
        return $this->post($fields + ['action' => 'save', 'id' => '0', 'parent' => '0', 'title' => '', 'slug' => '', 'description' => '', 'visible' => '1']);
    }

    /**
     * @param array<string, string> $fields
     */
    private function post(array $fields): Response
    {
        $page = $this->admin->get('/admin/rubrics/');

        return $this->admin->post('/admin/rubrics/', ['_csrf' => Browser::field($page, '_csrf')] + $fields);
    }

    private function id(string $slug): int
    {
        return (int) $this->db->value('SELECT id FROM rubrics WHERE slug = :slug', ['slug' => $slug]);
    }
}
