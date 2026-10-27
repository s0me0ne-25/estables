<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Core\Logger;
use Estables\Core\Services;
use Estables\Tests\Browser;

/**
 * Maintenance mode: guests see a stub, administrators see the site.
 */
final class MaintenanceTest extends DatabaseTestCase
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
        $rubric = $this->services->rubrics()->create(['parent' => 0, 'name' => 'ЛФЗ', 'title' => '', 'slug' => 'lfz', 'description' => '', 'visible' => true]);
        $this->services->items()->save(null, [
            'catno' => 'ЛФЗ-1042', 'name' => 'Конь вздыбленный', 'summary' => '', 'description' => '', 'yearFrom' => '', 'yearTo' => '',
            'published' => true, 'rubrics' => [$rubric], 'main' => $rubric, 'attributes' => [], 'variants' => [],
        ], null);
        $this->guest = new Browser($this->site->paths);
        $this->admin = new Browser($this->site->paths);
        $form = $this->admin->get('/login/');
        $this->admin->post('/login/', ['_token' => Browser::field($form, '_token'), 'login' => 'keeper', 'password' => self::PASSWORD, 'captcha_confirm' => '1']);
    }

    public function testSwitchClosesTheSiteForGuestsOnly(): void
    {
        $page = $this->admin->get('/admin/users/');
        self::assertStringContainsString('id="maintenance" name="maintenance" value="1">', $page->body(), 'Off by default');
        self::assertSame(403, $this->admin->post('/admin/users/', ['action' => 'maintenance', 'maintenance' => '1'])->status(), 'CSRF is checked');

        $on = $this->switch('1');
        self::assertSame(303, $on->status());
        self::assertSame('/admin/users/?done=maintenance-on', $on->header('Location'));
        $users = $this->admin->get((string) $on->header('Location'))->body();
        self::assertStringContainsString('Режим техработ включён', $users);
        self::assertStringContainsString('name="maintenance" value="1" checked>', $users);
        $entry = $this->db->row('SELECT action, actor FROM audit_log ORDER BY id DESC LIMIT 1');
        self::assertSame(['action' => 'site.maintenance-on', 'actor' => 'keeper'], $entry);

        foreach (['/', '/catalog/lfz/', '/item/lfz-1042/', '/about/', '/search/?q=конь', '/sitemap.xml', '/no-such-page/'] as $url) {
            $stub = $this->guest->get($url);
            self::assertSame(503, $stub->status(), $url);
            self::assertStringContainsString('<h1 class="mb-3">Ведутся технические работы</h1>', $stub->body(), $url);
            self::assertStringNotContainsString('Конь вздыбленный', $stub->body(), $url);
        }
        $stub = $this->guest->get('/');
        self::assertSame('3600', $stub->header('Retry-After'));
        self::assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $stub->body());
        self::assertStringContainsString('href="/login/">Вход администратора</a>', $stub->body(), 'The sign-in link stays in the footer');
        self::assertSame(200, $this->guest->get('/login/')->status());
        self::assertSame(200, $this->guest->get('/robots.txt')->status());

        $item = $this->admin->get('/item/lfz-1042/');
        self::assertSame(200, $item->status());
        self::assertStringContainsString('Конь вздыбленный', $item->body());
        self::assertStringContainsString('Включён режим техработ', $item->body(), 'Administrators are reminded');

        // A guest who signs in sees the site
        $late = new Browser($this->site->paths);
        $form = $late->get('/login/');
        $late->post('/login/', ['_token' => Browser::field($form, '_token'), 'login' => 'keeper', 'password' => self::PASSWORD, 'captcha_confirm' => '1']);
        self::assertSame(200, $late->get('/')->status());

        $this->switch('0');
        self::assertSame(200, $this->guest->get('/item/lfz-1042/')->status());
        self::assertStringNotContainsString('Включён режим техработ', $this->admin->get('/')->body());
        self::assertSame('site.maintenance-off', $this->db->value('SELECT action FROM audit_log ORDER BY id DESC LIMIT 1'));
    }

    private function switch(string $value): \Estables\Core\Response
    {
        $page = $this->admin->get('/admin/users/');

        return $this->admin->post('/admin/users/', ['_csrf' => Browser::field($page, '_csrf'), 'action' => 'maintenance', 'maintenance' => $value]);
    }
}
