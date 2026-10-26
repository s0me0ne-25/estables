<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Core\Logger;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Tests\Browser;

/**
 * Items in the admin area and on the public site, request by request.
 */
final class ItemPagesTest extends DatabaseTestCase
{
    private const PASSWORD = 'correct horse battery';

    private Services $services;
    private Browser $admin;
    private Browser $guest;
    private int $lfz;
    private int $sets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = new Services($this->site->paths, $this->config, new Logger($this->site->paths->var() . '/log/estables.log'));
        $this->services->schema()->ensureCurrent();
        $this->services->users()->create('keeper', self::PASSWORD, '');
        $this->services->installState()->markInstalled();
        $rubrics = $this->services->rubrics();
        $figures = $rubrics->create(['parent' => 0, 'name' => 'Фигурки', 'title' => '', 'slug' => '', 'description' => '', 'visible' => true]);
        $this->lfz = $rubrics->create(['parent' => $figures, 'name' => 'ЛФЗ', 'title' => 'Ленинградский фарфоровый завод', 'slug' => 'lfz', 'description' => '', 'visible' => true]);
        $this->sets = $rubrics->create(['parent' => 0, 'name' => 'Подборки', 'title' => '', 'slug' => '', 'description' => '', 'visible' => true]);
        $this->guest = new Browser($this->site->paths);
        $this->admin = new Browser($this->site->paths);
        $form = $this->admin->get('/login/');
        $this->admin->post('/login/', ['_token' => Browser::field($form, '_token'), 'login' => 'keeper', 'password' => self::PASSWORD, 'captcha_confirm' => '1']);
    }

    public function testEditorCreatesAnItemThatAppearsOnTheSite(): void
    {
        $invalid = $this->saveItem('/admin/items/new/', ['name' => '', 'rubrics' => []]);
        self::assertSame(422, $invalid->status());
        self::assertStringContainsString('Укажите название.', $invalid->body());
        self::assertStringContainsString('Добавьте хотя бы один раздел.', $invalid->body());

        $created = $this->saveItem('/admin/items/new/', [
            'catno' => 'ЛФЗ-1042', 'name' => 'Конь вздыбленный', 'year_from' => '1958', 'year_to' => '1979',
            'summary' => 'Одна из первых моделей.', 'description' => "Абзац один.\n\nАбзац два.",
            'rubrics' => [(string) $this->lfz, (string) $this->sets], 'main_rubric' => (string) $this->lfz,
            'attr_name' => ['Материал', '', 'Высота'], 'attr_value' => ['Фарфор', '', '21 см'],
            'var_number' => ['1042-a'], 'var_finish' => ['Белый бисквит'], 'var_years' => ['1958–1965'],
        ]);
        self::assertSame(303, $created->status());
        self::assertMatchesRegularExpression('~^/admin/items/\d+/\?done=created$~', (string) $created->header('Location'));
        self::assertStringContainsString('Предмет добавлен.', $this->admin->get((string) $created->header('Location'))->body());

        $page = $this->guest->get('/item/lfz-1042/');
        self::assertSame(200, $page->status());
        $body = $page->body();
        self::assertStringContainsString('<li class="breadcrumb-item"><a href="/catalog/figurki/lfz/">ЛФЗ</a></li>', $body);
        self::assertStringContainsString('<dt>Годы выпуска</dt><dd>1958–1979</dd>', $body);
        self::assertStringContainsString('<dt>Материал</dt><dd>Фарфор</dd>', $body);
        self::assertStringContainsString('<dt>Также в разделах</dt>', $body);
        self::assertStringContainsString('<td class="es-catno">1042-a</td>', $body);
        self::assertStringContainsString('<p>Абзац два.</p>', $body);
        self::assertStringNotContainsString('Редактировать', $body, 'Guests get no edit button');
        self::assertStringContainsString('Редактировать', $this->admin->get('/item/lfz-1042/')->body());

        $rubric = $this->guest->get('/catalog/figurki/lfz/');
        self::assertStringContainsString('<a href="/item/lfz-1042/">Конь вздыбленный</a>', $rubric->body());
        self::assertStringContainsString('<div><dt>Моделей</dt><dd>1</dd></div>', $rubric->body());
        self::assertStringContainsString('1958, 1 вариант', $rubric->body());
        self::assertStringContainsString('<span class="es-count">1</span>', $this->guest->get('/')->body());
    }

    public function testDraftsAndRedirects(): void
    {
        $id = $this->item('ЛФЗ-1', 'Черновик', ['status' => 'draft']);
        self::assertSame(404, $this->guest->get('/item/lfz-1/')->status());
        $page = $this->admin->get('/item/lfz-1/');
        self::assertSame(200, $page->status());
        self::assertStringContainsString('Черновик: посетители этот предмет не видят.', $page->body());
        self::assertStringNotContainsString('Черновик</a>', $this->guest->get('/catalog/figurki/lfz/')->body());

        $this->saveItem("/admin/items/{$id}/", ['catno' => 'ЛФЗ-2', 'name' => 'Черновик']);
        $redirect = $this->guest->get('/item/lfz-1/');
        self::assertSame(301, $redirect->status());
        self::assertSame('/item/lfz-2/', $redirect->header('Location'));
    }

    public function testSortCookieDecadesPagesAndSearch(): void
    {
        $this->item('Б-10', 'Альфа', ['year_from' => '1965']);
        $this->item('Б-9', 'Вега', ['year_from' => '1958']);
        $this->item('Б-100', 'Гамма', ['year_from' => '1952', 'summary' => 'Редкий рысак']);

        $cards = static fn(Response $r): array => preg_match_all('~<span class="es-catno">([^<]+)</span>~', $r->body(), $m) ? $m[1] : [];
        self::assertSame(['Б-100', 'Б-9', 'Б-10'], $cards($this->guest->get('/catalog/figurki/lfz/')), 'Authorial: the newest on top');
        self::assertSame(['Б-9', 'Б-10', 'Б-100'], $cards($this->guest->get('/catalog/figurki/lfz/?sort=number')));
        $this->guest->cookies['es_sort'] = 'year';
        self::assertSame(['Б-100', 'Б-9', 'Б-10'], $cards($this->guest->get('/catalog/figurki/lfz/')));
        self::assertSame(['Б-10', 'Б-9', 'Б-100'], $cards($this->guest->get('/catalog/figurki/lfz/?sort=title')), '?sort= beats the cookie');
        $decade = $this->guest->get('/catalog/figurki/lfz/?decade=1950');
        self::assertSame(['Б-100', 'Б-9'], $cards($decade));
        self::assertStringContainsString('href="/catalog/figurki/lfz/?decade=1960">1960-е <small>1</small></a>', $decade->body());

        $search = $this->guest->get('/search/?q=' . rawurlencode('рысак'));
        self::assertSame(['Б-100'], $cards($search));
        self::assertStringContainsString('найдено: 1 предмет', $search->body());
        self::assertStringContainsString('value="рысак"', $search->body(), 'The query stays in the header field');
        self::assertStringContainsString('ничего не нашлось', $this->guest->get('/search/?q=xyzzy')->body());
    }

    public function testAdminListOrderAndBulkActions(): void
    {
        $a = $this->item('В-1', 'Первый');
        $b = $this->item('В-2', 'Второй');
        $c = $this->item('В-3', 'Третий');

        $list = $this->admin->get("/admin/?rubric={$this->lfz}");
        self::assertStringContainsString('data-es-order data-es-sortable="list"', $list->body());
        self::assertStringContainsString('3 предмета в каталоге', $list->body());
        $csrf = Browser::field($list, '_csrf');

        self::assertSame(403, $this->admin->post('/admin/items/order/', ['rubric' => (string) $this->lfz, 'item' => (string) $c, 'after' => (string) $a])->status());
        $ok = $this->admin->post('/admin/items/order/', ['_csrf' => $csrf, 'rubric' => (string) $this->lfz, 'item' => (string) $c, 'after' => (string) $a]);
        self::assertSame('{"ok":true}', $ok->body());
        self::assertSame([$b, $a, $c], $this->order($this->lfz));

        $back = "/admin/?rubric={$this->lfz}";
        $bulk = fn(array $fields): Response => $this->admin->post('/admin/items/bulk/', $fields + ['_csrf' => $csrf, 'back' => $back, 'rubric' => (string) $this->lfz]);
        $done = $bulk(['action' => 'position', 'position' => '1', 'ids' => [(string) $c]]);
        self::assertSame("/admin/?rubric={$this->lfz}&done=ordered", $done->header('Location'));
        self::assertSame([$c, $b, $a], $this->order($this->lfz));

        $bulk(['action' => 'unpublish', 'ids' => [(string) $a, (string) $b]]);
        self::assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM items WHERE is_published = 0'));
        $added = $bulk(['action' => 'add-rubric', 'target_add' => (string) $this->sets, 'ids' => [(string) $a]]);
        self::assertStringContainsString('done=added&n=1', (string) $added->header('Location'));
        $removed = $bulk(['action' => 'remove-rubric', 'target_remove' => (string) $this->lfz, 'ids' => [(string) $a, (string) $b]]);
        self::assertStringContainsString('done=removed&n=1&kept=1', (string) $removed->header('Location'));
        self::assertStringContainsString('У 1 предмета этот раздел единственный', $this->admin->get((string) $removed->header('Location'))->body());
        $bulk(['action' => 'delete', 'ids' => [(string) $b]]);
        self::assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM items'));
        self::assertSame(303, $bulk(['action' => 'publish', 'ids' => [], 'back' => 'https://evil.example/'])->status());
        self::assertSame('/admin/?done=none', $bulk(['action' => 'publish', 'back' => 'https://evil.example/'])->header('Location'));
    }

    public function testDeletingARubricMovesOrDeletesItsItems(): void
    {
        $own = $this->item('Г-1', 'Только здесь');
        $shared = $this->item('Г-2', 'И в подборке', ['rubrics' => [(string) $this->lfz, (string) $this->sets]]);
        $page = $this->admin->get("/admin/rubrics/?id={$this->lfz}");
        self::assertStringContainsString('data-es-rubric-items="2" data-es-rubric-shared="1"', $page->body());
        $csrf = Browser::field($page, '_csrf');

        $refused = $this->admin->post('/admin/rubrics/', ['_csrf' => $csrf, 'action' => 'delete', 'id' => (string) $this->lfz]);
        self::assertSame(422, $refused->status(), 'Own items need an explicit choice');

        $moved = $this->admin->post('/admin/rubrics/', ['_csrf' => $csrf, 'action' => 'delete', 'id' => (string) $this->lfz, 'items' => 'move', 'target' => (string) $this->sets]);
        self::assertSame(303, $moved->status());
        self::assertEqualsCanonicalizing([$own, $shared], $this->order($this->sets));
        self::assertSame([$this->sets], $this->services->items()->rubricsOf($own), 'The item moved with its main link');
        self::assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM items'));
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

    /**
     * @phpstan-impure
     *
     * @return list<int>
     */
    private function order(int $rubric): array
    {
        return $this->db->ints('SELECT item_id FROM item_rubrics WHERE rubric_id = :r ORDER BY position, item_id', ['r' => $rubric]);
    }
}
