<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Cli\Application;
use Estables\Core\Logger;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Tests\Browser;

/**
 * The journal of administrators' actions and its page.
 */
final class AuditTest extends DatabaseTestCase
{
    private const PASSWORD = 'correct horse battery';

    private Services $services;
    private Browser $admin;
    private int $lfz;
    private int $sets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = new Services($this->site->paths, $this->config, new Logger($this->site->paths->var() . '/log/estables.log'));
        $this->services->schema()->ensureCurrent();
        $this->services->users()->create('keeper', self::PASSWORD, '');
        $this->services->users()->create('volkov', self::PASSWORD, '');
        $this->services->installState()->markInstalled();
        $rubrics = $this->services->rubrics();
        $this->lfz = $rubrics->create(['parent' => 0, 'name' => 'ЛФЗ', 'title' => '', 'slug' => 'lfz', 'description' => '', 'visible' => true]);
        $this->sets = $rubrics->create(['parent' => 0, 'name' => 'Подборки', 'title' => '', 'slug' => 'sets', 'description' => '', 'visible' => true]);
        $this->admin = $this->signIn('keeper');
    }

    public function testLoginAndLogoutAreRecorded(): void
    {
        $entry = $this->last();
        self::assertSame(['login', 'keeper', 'keeper', '203.0.113.24'], [$entry['action'], $entry['actor'], $entry['subject'], $entry['ip']]);
        $page = $this->admin->get('/admin/');
        $this->admin->post('/logout/', ['_csrf' => Browser::field($page, '_csrf')]);
        self::assertSame('logout', $this->last()['action']);
    }

    public function testItemChangesAreDescribed(): void
    {
        $created = $this->saveItem('/admin/items/new/', ['catno' => 'ЛФЗ-1042', 'name' => 'Конь', 'status' => 'draft']);
        self::assertSame(303, $created->status());
        $id = (int) $this->db->value("SELECT id FROM items WHERE catno = 'ЛФЗ-1042'");
        $entry = $this->last();
        self::assertSame(['item.create', 'ЛФЗ-1042 Конь', $id, 'черновик'], [$entry['action'], $entry['subject'], (int) $entry['object_id'], $entry['details']]);

        $this->saveItem("/admin/items/{$id}/", [
            'catno' => 'ЛФЗ-1042а', 'name' => 'Конь вздыбленный', 'status' => 'published',
            'rubrics' => [(string) $this->lfz, (string) $this->sets], 'main_rubric' => (string) $this->lfz,
            'attr_name' => ['Материал'], 'attr_value' => ['Фарфор'],
        ]);
        $entry = $this->last();
        self::assertSame('item.update', $entry['action']);
        self::assertSame('ЛФЗ-1042а Конь вздыбленный', $entry['subject']);
        self::assertSame('номер: ЛФЗ-1042 → ЛФЗ-1042а; опубликован; название; + «Подборки»; характеристики', $entry['details']);

        $count = $this->entries();
        $this->saveItem("/admin/items/{$id}/", [
            'catno' => 'ЛФЗ-1042а', 'name' => 'Конь вздыбленный', 'status' => 'published',
            'rubrics' => [(string) $this->lfz, (string) $this->sets], 'main_rubric' => (string) $this->lfz,
            'attr_name' => ['Материал'], 'attr_value' => ['Фарфор'],
        ]);
        self::assertSame($count, $this->entries(), 'Saving without changes adds nothing');

        $list = $this->admin->get('/admin/');
        $this->admin->post('/admin/items/bulk/', ['_csrf' => Browser::field($list, '_csrf'), 'action' => 'unpublish', 'ids' => [(string) $id], 'back' => '/admin/']);
        self::assertSame(['items.unpublish', '1 предмет', 'ЛФЗ-1042а Конь вздыбленный'], [$this->last()['action'], $this->last()['subject'], $this->last()['details']]);

        $editor = $this->admin->get("/admin/items/{$id}/");
        $this->admin->post("/admin/items/{$id}/", ['_csrf' => Browser::field($editor, '_csrf'), 'action' => 'delete']);
        $entry = $this->last();
        self::assertSame(['item.delete', 'ЛФЗ-1042а Конь вздыбленный'], [$entry['action'], $entry['subject']]);

        $journal = $this->admin->get('/admin/audit/')->body();
        self::assertStringContainsString('<td>Удалён предмет</td>', $journal);
        self::assertStringContainsString('<td class="es-col-title">ЛФЗ-1042 Конь</td>', $journal, 'A deleted item has no link');
        self::assertStringNotContainsString('href="/admin/items/' . $id . '/"', $journal);
        self::assertStringContainsString('номер: ЛФЗ-1042 → ЛФЗ-1042а', $journal);
    }

    public function testRubricChangesAreRecorded(): void
    {
        $page = $this->admin->get('/admin/rubrics/');
        $this->admin->post('/admin/rubrics/', ['_csrf' => Browser::field($page, '_csrf'), 'action' => 'save', 'id' => (string) $this->sets,
            'parent' => (string) $this->lfz, 'name' => 'Подборки ЛФЗ', 'title' => '', 'slug' => 'sets', 'description' => '', 'visible' => '0']);
        $entry = $this->last();
        self::assertSame(['rubric.update', 'Подборки ЛФЗ', 'название: Подборки → Подборки ЛФЗ; перенесён в «ЛФЗ»; скрыт'], [$entry['action'], $entry['subject'], $entry['details']]);

        $this->admin->post('/admin/rubrics/move/', ['_csrf' => Browser::field($page, '_csrf'), 'id' => (string) $this->sets, 'parent' => '0', 'index' => '0']);
        self::assertSame(['rubric.move', 'перенесён на верхний уровень, позиция 1'], [$this->last()['action'], $this->last()['details']]);

        $this->admin->post('/admin/rubrics/', ['_csrf' => Browser::field($page, '_csrf'), 'action' => 'delete', 'id' => (string) $this->sets]);
        self::assertSame(['rubric.delete', 'Подборки ЛФЗ'], [$this->last()['action'], $this->last()['subject']]);
    }

    public function testJournalFiltersAndConsoleEntries(): void
    {
        $volkov = $this->signIn('volkov');
        $users = $volkov->get('/admin/users/');
        $keeper = (int) $this->db->value("SELECT id FROM users WHERE login = 'keeper'");
        $volkov->post('/admin/users/', ['_csrf' => Browser::field($users, '_csrf'), 'action' => 'block', 'user' => (string) $keeper]);
        self::assertSame(['user.block', 'volkov', 'keeper'], [$this->last()['action'], $this->last()['actor'], $this->last()['subject']]);

        $out = fopen('php://memory', 'r+');
        self::assertIsResource($out);
        self::assertSame(0, new Application($this->site->paths, null, $out, $out)->run(['unlock', 'keeper']));
        $entry = $this->last();
        self::assertSame(['user.unlock', '', null, ''], [$entry['action'], $entry['actor'], $entry['user_id'], $entry['ip']]);

        $page = $volkov->get('/admin/audit/');
        self::assertSame(200, $page->status());
        $body = $page->body();
        self::assertStringContainsString('<option value="cli">Консоль</option>', $body);
        self::assertStringContainsString('<span class="text-body-secondary">Консоль</span>', $body);
        self::assertStringContainsString('aria-current="page" href="/admin/audit/"', $body, 'The menu item is active');

        $onlyUsers = $volkov->get('/admin/audit/?what=users')->body();
        self::assertStringContainsString('<td>Заблокирован</td>', $onlyUsers);
        self::assertStringNotContainsString('<td>Вход</td>', $onlyUsers);
        $onlyConsole = $volkov->get('/admin/audit/?who=cli')->body();
        self::assertSame(1, substr_count($onlyConsole, '<tr>') - 1, 'One console entry plus the header row');

        $this->db->run('UPDATE audit_log SET created_at = UTC_TIMESTAMP() - INTERVAL 800 DAY WHERE user_id IS NULL');
        self::assertSame(1, $this->services->audit()->purge(730));
    }

    public function testFailedLoginLinesCannotBeForged(): void
    {
        $browser = new Browser($this->site->paths);
        $form = $browser->get('/login/');
        $browser->post('/login/', ['_token' => Browser::field($form, '_token'), 'login' => "x\" from 198.51.100.7\n[2026-01-01 00:00:00] WARNING: Login failed for \"y", 'password' => 'nope', 'captcha_confirm' => '1']);
        $log = (string) file_get_contents($this->site->paths->var() . '/log/estables.log');
        self::assertStringContainsString('Login failed for "x\\" from 198.51.100.7\\x0a[2026-01-01 00:00:00] WARNING: Login failed for \\"y" from 203.0.113.24' . "\n", $log);
    }

    private function signIn(string $login): Browser
    {
        $browser = new Browser($this->site->paths);
        $form = $browser->get('/login/');
        $browser->post('/login/', ['_token' => Browser::field($form, '_token'), 'login' => $login, 'password' => self::PASSWORD, 'captcha_confirm' => '1']);

        return $browser;
    }

    /**
     * @return array<string, mixed>
     */
    private function last(): array
    {
        $row = $this->db->row('SELECT * FROM audit_log ORDER BY id DESC LIMIT 1');
        self::assertNotNull($row);

        return $row;
    }

    private function entries(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM audit_log');
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
}
