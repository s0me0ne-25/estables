<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Core\Logger;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Tests\Browser;

/**
 * Sign-in, sessions and sign-out, request by request.
 */
final class LoginFlowTest extends DatabaseTestCase
{
    private const PASSWORD = 'correct horse battery';

    private Services $services;
    private Browser $browser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = new Services($this->site->paths, $this->config, new Logger($this->site->paths->var() . '/log/estables.log'));
        $this->services->schema()->ensureCurrent();
        $this->services->users()->create('keeper', self::PASSWORD, 'Хранитель');
        $this->services->installState()->markInstalled();
        $this->browser = new Browser($this->site->paths);
    }

    public function testAdminAreaRequiresLogin(): void
    {
        $response = $this->browser->get('/admin/users/');
        self::assertSame(302, $response->status());
        self::assertSame('/login/?next=%2Fadmin%2Fusers%2F', $response->header('Location'));
        self::assertSame([], $this->browser->cookies, 'Guests get no cookies');
    }

    public function testAboutPageAndMenu(): void
    {
        $about = $this->browser->get('/about/');
        self::assertSame(200, $about->status());
        self::assertStringContainsString('<a class="nav-link active" aria-current="page" href="/about/">О справочнике</a>', $about->body());
        // The text itself belongs to the site owner; only the frame is checked
        self::assertStringContainsString('<div class="es-about">', $about->body());
        // Error pages link the menu like any other page
        self::assertStringContainsString('href="/about/">О справочнике</a>', $this->browser->get('/no/such/page/')->body());
    }

    public function testGuestFooterAndLoginForm(): void
    {
        self::assertStringContainsString('href="/login/">Вход администратора', $this->browser->get('/')->body());
        self::assertSame([], $this->browser->cookies);

        $form = $this->browser->get('/login/');
        self::assertSame(200, $form->status());
        self::assertSame('no-store', $form->header('Cache-Control'));
        self::assertStringContainsString('noindex', $form->body());
        self::assertStringContainsString('Запомнить меня', $form->body());
        self::assertStringContainsString('name="captcha_confirm"', $form->body());
    }

    public function testWrongPasswordAndMissingCaptcha(): void
    {
        $wrong = $this->login(['password' => 'wrong password!']);
        self::assertSame(422, $wrong->status());
        self::assertStringContainsString('Неверный логин или пароль', $wrong->body());
        self::assertStringContainsString('value="keeper"', $wrong->body(), 'The login is kept');
        self::assertStringNotContainsString('wrong password!', $wrong->body());

        $unknown = $this->login(['login' => 'nobody']);
        self::assertStringContainsString('Неверный логин или пароль', $unknown->body());

        $noCaptcha = $this->login(['captcha_confirm' => '']);
        self::assertSame(422, $noCaptcha->status());
        self::assertStringContainsString('Подтвердите, что вы человек', $noCaptcha->body());

        self::assertSame(2, (int) $this->db->value('SELECT COUNT(*) FROM login_attempts WHERE success = 0'));
        $log = (string) file_get_contents($this->site->paths->var() . '/log/estables.log');
        self::assertStringContainsString('Login failed for "keeper" from 203.0.113.24', $log);
    }

    public function testLoginWithoutRememberIsABrowserSessionCookie(): void
    {
        $response = $this->login();
        self::assertSame(303, $response->status());
        self::assertSame('/admin/', $response->header('Location'));
        $cookie = $this->browser->lastSetCookies['es_session'] ?? '';
        self::assertMatchesRegularExpression('~^es_session=[A-Za-z0-9_-]{43}; Path=/; SameSite=Lax; HttpOnly$~', $cookie);

        // The database keeps only the hash of the cookie value
        $value = $this->browser->cookies['es_session'];
        self::assertSame(hash('sha256', $value, true), $this->db->value('SELECT id_hash FROM sessions'));
        self::assertSame(0, (int) $this->db->value('SELECT remember FROM sessions'));
        self::assertNotNull($this->db->value('SELECT last_login_at FROM users'));

        $admin = $this->browser->get('/admin/');
        self::assertSame(200, $admin->status());
        self::assertStringContainsString('администрирование', $admin->body());
        self::assertSame('private, no-store', $admin->header('Cache-Control'));
        self::assertStringContainsString('Вы — администратор', $admin->body());

        // The login form is skipped while signed in
        self::assertSame('/admin/', $this->browser->get('/login/')->header('Location'));
        // The public site shows the admin footer
        self::assertStringContainsString('action="/logout/"', $this->browser->get('/')->body());
    }

    public function testRememberMeSetsMaxAge(): void
    {
        $this->login(['remember' => '1']);
        self::assertStringContainsString('; Max-Age=2592000;', $this->browser->lastSetCookies['es_session'] ?? '');
        $expires = new \DateTimeImmutable((string) $this->db->value('SELECT expires_at FROM sessions'), new \DateTimeZone('UTC'));
        self::assertEqualsWithDelta(time() + 30 * 86400, $expires->getTimestamp(), 5);
    }

    public function testReturnsToTheRequestedPageButNeverToAnotherSite(): void
    {
        $response = $this->login([], '/login/?next=' . rawurlencode('/admin/users/'));
        self::assertSame('/admin/users/', $response->header('Location'));

        $this->browser = new Browser($this->site->paths);
        $response = $this->login([], '/login/?next=' . rawurlencode('//evil.example/'));
        self::assertSame('/admin/', $response->header('Location'));
    }

    public function testLogoutNeedsTheCsrfToken(): void
    {
        $this->login();
        $page = $this->browser->get('/admin/');

        self::assertSame(403, $this->browser->post('/logout/', ['_csrf' => 'forged'])->status());
        self::assertSame(1, (int) $this->db->value('SELECT COUNT(*) FROM sessions'));

        $response = $this->browser->post('/logout/', ['_csrf' => Browser::field($page, '_csrf')]);
        self::assertSame(303, $response->status());
        self::assertSame('/', $response->header('Location'));
        self::assertStringContainsString('es_session=; Path=/; SameSite=Lax; Max-Age=0', $this->browser->lastSetCookies['es_session'] ?? '');
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM sessions'));
        self::assertSame(302, $this->browser->get('/admin/')->status());
    }

    public function testUnknownCookieIsDroppedNotRevived(): void
    {
        $this->browser->cookies['es_session'] = str_repeat('A', 43);
        $response = $this->browser->get('/');
        self::assertStringContainsString('Вход администратора', $response->body());
        self::assertArrayNotHasKey('es_session', $this->browser->cookies);
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM sessions'));
    }

    public function testIdleSessionExpires(): void
    {
        $this->login();
        $this->db->exec('UPDATE sessions SET expires_at = UTC_TIMESTAMP() - INTERVAL 1 MINUTE');
        self::assertSame(302, $this->browser->get('/admin/')->status());
        self::assertArrayNotHasKey('es_session', $this->browser->cookies);
    }

    public function testActivityExtendsTheSession(): void
    {
        $this->login(['remember' => '1']);
        $this->db->exec('UPDATE sessions SET last_seen_at = UTC_TIMESTAMP() - INTERVAL 1 HOUR, expires_at = UTC_TIMESTAMP() + INTERVAL 1 DAY');
        $this->browser->get('/admin/');
        $expires = new \DateTimeImmutable((string) $this->db->value('SELECT expires_at FROM sessions'), new \DateTimeZone('UTC'));
        self::assertEqualsWithDelta(time() + 30 * 86400, $expires->getTimestamp(), 5);
        self::assertStringContainsString('Max-Age=2592000', $this->browser->lastSetCookies['es_session'] ?? '', 'The cookie slides too');
    }

    public function testIdentifierRotationKeepsTheOldOneForAMinute(): void
    {
        $this->login();
        $old = $this->browser->cookies['es_session'];
        $this->db->exec('UPDATE sessions SET rotated_at = UTC_TIMESTAMP() - INTERVAL 25 HOUR');

        self::assertSame(200, $this->browser->get('/admin/')->status());
        $new = $this->browser->cookies['es_session'];
        self::assertNotSame($old, $new);
        self::assertSame(hash('sha256', $new, true), $this->db->value('SELECT id_hash FROM sessions'));

        // A parallel request with the old cookie still passes...
        $parallel = new Browser($this->site->paths);
        $parallel->cookies['es_session'] = $old;
        self::assertSame(200, $parallel->get('/admin/')->status());
        self::assertArrayNotHasKey('es_session', $parallel->lastSetCookies);

        // ...but not after the grace period
        $this->db->exec('UPDATE sessions SET previous_valid_until = UTC_TIMESTAMP() - INTERVAL 1 SECOND');
        self::assertSame(302, $parallel->get('/admin/')->status());
        self::assertSame(200, $this->browser->get('/admin/')->status());
    }

    public function testBruteForceLocksTheLogin(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login(['password' => 'wrong password ' . $i]);
        }
        $locked = $this->login();
        self::assertSame(422, $locked->status());
        self::assertStringContainsString('Слишком много неудачных попыток. Попробуйте снова через 15 минут.', $locked->body());
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM sessions'));

        // Failures leave the window
        $this->db->exec('UPDATE login_attempts SET attempted_at = attempted_at - INTERVAL 16 MINUTE');
        self::assertSame(303, $this->login()->status());
    }

    public function testBlockedUserCannotSignIn(): void
    {
        $this->db->exec('UPDATE users SET is_active = 0');
        $response = $this->login();
        self::assertSame(422, $response->status());
        self::assertStringContainsString('Учётная запись заблокирована', $response->body());
    }

    public function testLoginFormRejectsMissingFormToken(): void
    {
        $this->browser->get('/login/');
        $response = $this->browser->post('/login/', ['login' => 'keeper', 'password' => self::PASSWORD, 'captcha_confirm' => '1']);
        self::assertSame(422, $response->status());
        self::assertStringContainsString('Форма устарела', $response->body());
    }

    /**
     * Opens the form and submits it.
     *
     * @param array<string, string> $fields overrides of the default fields
     */
    private function login(array $fields = [], string $url = '/login/'): Response
    {
        $form = $this->browser->get($url);

        return $this->browser->post($url, array_filter($fields + [
            '_token' => Browser::field($form, '_token'),
            'login' => 'keeper',
            'password' => self::PASSWORD,
            'captcha_confirm' => '1',
        ], static fn(string $value): bool => $value !== ''));
    }
}
