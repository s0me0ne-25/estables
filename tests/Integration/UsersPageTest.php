<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Core\Logger;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Tests\Browser;

/**
 * Admin page "Users": accounts and sessions.
 */
final class UsersPageTest extends DatabaseTestCase
{
    private const PASSWORD = 'correct horse battery';

    private Services $services;
    private Browser $browser;
    private int $myId;
    private int $otherId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = new Services($this->site->paths, $this->config, new Logger($this->site->paths->var() . '/log/estables.log'));
        $this->services->schema()->ensureCurrent();
        $this->myId = $this->services->users()->create('keeper', self::PASSWORD, 'Хранитель');
        $this->otherId = $this->services->users()->create('ekovaleva', self::PASSWORD, 'Елена Ковалёва');
        $this->services->installState()->markInstalled();
        $this->browser = $this->signIn('keeper');
    }

    public function testListsUsersAndMySessions(): void
    {
        $this->signIn('keeper', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1');
        $this->signIn('ekovaleva');

        $page = $this->browser->get('/admin/users/');
        self::assertSame(200, $page->status());
        $body = $page->body();
        self::assertStringContainsString('keeper <span class="es-meta d-inline">(вы)</span>', $body);
        self::assertStringContainsString('Елена Ковалёва', $body);
        self::assertStringContainsString('Firefox, Linux', $body);
        self::assertStringContainsString('bi-phone', $body);
        self::assertStringContainsString('Safari, iOS', $body);
        self::assertStringContainsString('это устройство', $body);
        self::assertSame(1, substr_count($body, 'name="action" value="block"'), 'Nobody can block themselves');
    }

    public function testChangingFormsWithoutTheTokenIsForbidden(): void
    {
        $response = $this->browser->post('/admin/users/', ['action' => 'block', 'user' => (string) $this->otherId]);
        self::assertSame(403, $response->status());
        self::assertSame(1, (int) $this->db->value('SELECT is_active FROM users WHERE id = ' . $this->otherId));
    }

    public function testAddAdministrator(): void
    {
        $invalid = $this->act('add', ['login' => 'ekovaleva', 'display_name' => 'Двойник', 'password' => 'short', 'password_again' => 'short']);
        self::assertSame(422, $invalid->status());
        self::assertStringContainsString('data-es-modal-open="addUserModal"', $invalid->body());
        self::assertStringContainsString('Такой логин уже есть', $invalid->body());
        self::assertStringContainsString('<div class="invalid-feedback" id="u-pass-help">Не короче 12 символов.</div>', $invalid->body());
        self::assertStringNotContainsString('class="form-text" id="u-pass-help"', $invalid->body(), 'The error replaces the hint');
        self::assertStringContainsString('value="Двойник"', $invalid->body());

        $done = $this->act('add', ['login' => 'archive', 'display_name' => '', 'password' => self::PASSWORD, 'password_again' => self::PASSWORD]);
        self::assertSame(303, $done->status());
        self::assertSame('/admin/users/?done=added', $done->header('Location'));
        self::assertStringContainsString('Администратор добавлен.', $this->browser->get('/admin/users/?done=added')->body());
        self::assertSame(3, $this->services->users()->count());
    }

    public function testBlockingEndsSessionsAndUnblockingRestoresAccess(): void
    {
        $other = $this->signIn('ekovaleva');
        self::assertSame(303, $this->act('block', ['user' => (string) $this->otherId])->status());
        self::assertSame(302, $other->get('/admin/')->status());
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM sessions WHERE user_id = ' . $this->otherId));

        self::assertSame(400, $this->act('block', ['user' => (string) $this->myId])->status());

        $this->act('unblock', ['user' => (string) $this->otherId]);
        self::assertSame(1, (int) $this->db->value('SELECT is_active FROM users WHERE id = ' . $this->otherId));
    }

    public function testChangeOwnPasswordNeedsTheCurrentOne(): void
    {
        $phone = $this->signIn('keeper');
        $fields = ['user' => (string) $this->myId, 'password' => 'a new long password', 'password_again' => 'a new long password', 'end_sessions' => '1'];

        $wrong = $this->act('password', $fields + ['current_password' => 'not it']);
        self::assertSame(422, $wrong->status());
        self::assertStringContainsString('data-es-modal-open="passwordModal-' . $this->myId . '"', $wrong->body());
        self::assertStringContainsString('Текущий пароль введён неверно', $wrong->body());

        self::assertSame(303, $this->act('password', $fields + ['current_password' => self::PASSWORD])->status());
        self::assertSame(200, $this->browser->get('/admin/')->status(), 'This session stays');
        self::assertSame(302, $phone->get('/admin/')->status(), 'Other sessions end');
        self::assertTrue(password_verify('a new long password', (string) $this->db->value('SELECT password_hash FROM users WHERE id = ' . $this->myId)));
    }

    public function testChangeAnotherPasswordWithoutEndingSessions(): void
    {
        $other = $this->signIn('ekovaleva');
        $response = $this->act('password', ['user' => (string) $this->otherId, 'password' => 'a new long password', 'password_again' => 'a new long password']);
        self::assertSame(303, $response->status());
        self::assertSame(200, $other->get('/admin/')->status());
    }

    public function testEndOneSessionAndAllOthers(): void
    {
        $second = $this->signIn('keeper');
        $third = $this->signIn('keeper');
        $page = $this->browser->get('/admin/users/');
        preg_match_all('~name="session" value="([a-f0-9]{64})"~', $page->body(), $m);
        self::assertCount(2, $m[1]);

        $this->act('end-session', ['session' => $m[1][0]]);
        self::assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM sessions WHERE user_id = ' . $this->myId));

        $this->act('end-others', []);
        self::assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM sessions WHERE user_id = ' . $this->myId));
        self::assertSame(200, $this->browser->get('/admin/')->status());
        self::assertSame(302, $second->get('/admin/')->status());
        self::assertSame(302, $third->get('/admin/')->status());
    }

    public function testEndingAllOwnSessionsSignsOut(): void
    {
        $response = $this->act('end-sessions', ['user' => (string) $this->myId]);
        self::assertSame('/login/', $response->header('Location'));
        self::assertArrayNotHasKey('es_session', $this->browser->cookies);
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM sessions'));
    }

    public function testPlaceholdersOfLaterBuilds(): void
    {
        self::assertStringContainsString('появятся в сборке 4', $this->browser->get('/admin/')->body());
        self::assertStringContainsString('появится в сборке 3', $this->browser->get('/admin/rubrics/')->body());
    }

    private function signIn(string $login, ?string $userAgent = null): Browser
    {
        $browser = $userAgent === null ? new Browser($this->site->paths) : new Browser($this->site->paths, $userAgent);
        $form = $browser->get('/login/');
        $response = $browser->post('/login/', [
            '_token' => Browser::field($form, '_token'),
            'login' => $login,
            'password' => self::PASSWORD,
            'captcha_confirm' => '1',
        ]);
        self::assertSame(303, $response->status());

        return $browser;
    }

    /**
     * @param array<string, string> $fields
     */
    private function act(string $action, array $fields): Response
    {
        $page = $this->browser->get('/admin/users/');

        return $this->browser->post('/admin/users/', ['_csrf' => Browser::field($page, '_csrf'), 'action' => $action] + $fields);
    }
}
