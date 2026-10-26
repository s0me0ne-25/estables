<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Core\Kernel;
use Estables\Core\Request;
use Estables\Core\Response;

/**
 * The first run through the web installer, request by request.
 */
final class InstallFlowTest extends DatabaseTestCase
{
    public function testFirstRunCreatesSchemaAndAdministrator(): void
    {
        // Any page leads to the installer; the schema is created on the way.
        $response = $this->get('/');
        self::assertSame(302, $response->status());
        self::assertSame('/setup/', $response->header('Location'));
        self::assertContains('users', $this->tables());

        // The form, with the CSRF cookie
        $form = $this->get('/setup/');
        self::assertSame(200, $form->status());
        self::assertStringContainsString('Первый администратор', $form->body());
        $cookie = $this->formCookie($form);
        $csrf = $this->csrfToken($form);
        $token = trim((string) file_get_contents($this->site->paths->installToken()));

        // Wrong install token
        $wrong = $this->post('/setup/', $cookie, $csrf, ['install_token' => 'nope'] + $this->account());
        self::assertSame(422, $wrong->status());
        self::assertStringContainsString('Токен не подходит', $wrong->body());

        // Validation errors keep the entered values but never the password
        $invalid = $this->post('/setup/', $cookie, $csrf, ['install_token' => $token, 'password_again' => 'other'] + $this->account());
        self::assertSame(422, $invalid->status());
        self::assertStringContainsString('Пароли не совпадают', $invalid->body());
        self::assertStringNotContainsString('correct horse battery', $invalid->body());

        // Success
        $done = $this->post('/setup/', $cookie, $csrf, ['install_token' => $token] + $this->account());
        self::assertSame(200, $done->status());
        self::assertStringContainsString('Установка завершена', $done->body());
        self::assertFileDoesNotExist($this->site->paths->installToken());

        $user = $this->db->row('SELECT login, display_name, password_hash FROM users');
        self::assertSame('keeper', $user['login'] ?? null);
        self::assertSame('Хранитель', $user['display_name'] ?? null);
        self::assertTrue(password_verify('correct horse battery', (string) ($user['password_hash'] ?? '')));

        // The site is open, the installer is gone
        self::assertSame(200, $this->get('/')->status());
        self::assertSame(404, $this->get('/setup/')->status());
    }

    public function testFormRejectsMissingCsrfToken(): void
    {
        $this->get('/setup/');
        $token = trim((string) file_get_contents($this->site->paths->installToken()));
        $response = $this->post('/setup/', [], '', ['install_token' => $token] + $this->account());
        self::assertSame(422, $response->status());
        self::assertStringContainsString('Форма устарела', $response->body());
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM users'));
    }

    /**
     * @return array<string, string>
     */
    private function account(): array
    {
        return [
            'login' => 'keeper',
            'display_name' => 'Хранитель',
            'password' => 'correct horse battery',
            'password_again' => 'correct horse battery',
        ];
    }

    private function get(string $path): Response
    {
        return new Kernel($this->site->paths)->handle(new Request('GET', $path));
    }

    /**
     * @param array<string, string> $cookies
     * @param array<string, string> $fields
     */
    private function post(string $path, array $cookies, string $csrf, array $fields): Response
    {
        return new Kernel($this->site->paths)->handle(
            new Request('POST', $path, post: ['_token' => $csrf] + $fields, cookies: $cookies),
        );
    }

    /**
     * @return array<string, string>
     */
    private function formCookie(Response $response): array
    {
        foreach ($response->cookies() as $cookie) {
            if (preg_match('~^(es_form)=([a-f0-9]+);~', $cookie, $m)) {
                return [$m[1] => $m[2]];
            }
        }
        self::fail('No form cookie');
    }

    private function csrfToken(Response $response): string
    {
        if (!preg_match('~name="_token" value="([^"]+)"~', $response->body(), $m)) {
            self::fail('No CSRF token in the form');
        }

        return $m[1];
    }
}
