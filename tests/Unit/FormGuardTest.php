<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Unit;

use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Security\FormGuard;
use PHPUnit\Framework\TestCase;

final class FormGuardTest extends TestCase
{
    private const SECRET = 'test-secret-test-secret-test-secret';

    public function testTokenRoundTrip(): void
    {
        [$cookie, $token] = $this->issue('setup');
        self::assertTrue($this->guardFor($cookie, $token)->check('setup'));
    }

    public function testRejectsOtherPurposeMissingCookieAndTampering(): void
    {
        [$cookie, $token] = $this->issue('setup');
        self::assertFalse($this->guardFor($cookie, $token)->check('login'));
        self::assertFalse($this->guardFor('', $token)->check('setup'));
        self::assertFalse($this->guardFor(str_repeat('0', 32), $token)->check('setup'));
        self::assertFalse($this->guardFor($cookie, $token . 'x')->check('setup'));
        self::assertFalse($this->guardFor($cookie, '')->check('setup'));
    }

    public function testRejectsExpiredToken(): void
    {
        [$cookie, $token] = $this->issue('setup');
        [, $signature] = explode('.', $token, 2);
        self::assertFalse($this->guardFor($cookie, (time() - 10) . '.' . $signature)->check('setup'));
    }

    public function testReusesExistingCookie(): void
    {
        $guard = new FormGuard(self::SECRET, new Request('GET', '/', cookies: ['es_form' => str_repeat('a', 32)]));
        $guard->token('setup');
        self::assertSame([], $guard->apply(new Response())->cookies());
    }

    /**
     * @return array{string, string} cookie value and token
     */
    private function issue(string $purpose): array
    {
        $guard = new FormGuard(self::SECRET, new Request('GET', '/'));
        $token = $guard->token($purpose);
        $cookies = $guard->apply(new Response())->cookies();
        self::assertCount(1, $cookies);
        if (!preg_match('~^es_form=([a-f0-9]{32});~', $cookies[0], $m)) {
            self::fail('Unexpected cookie ' . $cookies[0]);
        }

        return [$m[1], $token];
    }

    private function guardFor(string $cookie, string $token): FormGuard
    {
        $cookies = $cookie === '' ? [] : ['es_form' => $cookie];

        return new FormGuard(self::SECRET, new Request('POST', '/', post: ['_token' => $token], cookies: $cookies));
    }
}
