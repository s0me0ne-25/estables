<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Unit;

use Estables\Auth\Captcha\CaptchaFactory;
use Estables\Auth\Captcha\StubCaptcha;
use Estables\Auth\Captcha\TurnstileCaptcha;
use Estables\Auth\UserAgent;
use Estables\Core\Config;
use Estables\Core\Logger;
use Estables\Core\Request;
use Estables\Http\AuthController;
use Estables\Security\Csp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Small pieces of sign-in that need no database.
 */
final class AuthHelpersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function targets(): iterable
    {
        yield 'admin page' => ['/admin/users/', '/admin/users/'];
        yield 'with query' => ['/admin/?a=b', '/admin/?a=b'];
        yield 'empty' => ['', '/admin/'];
        yield 'protocol-relative' => ['//evil.example/', '/admin/'];
        yield 'backslash' => ['/\\evil.example/', '/admin/'];
        yield 'absolute' => ['https://evil.example/', '/admin/'];
        yield 'header injection' => ["/admin/\r\nX: y", '/admin/'];
        yield 'login loop' => ['/login/', '/admin/'];
    }

    #[DataProvider('targets')]
    public function testSafeTarget(string $next, string $expected): void
    {
        self::assertSame($expected, AuthController::safeTarget($next));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function userAgents(): iterable
    {
        yield 'Firefox Linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0', 'Firefox, Linux', 'laptop'];
        yield 'Safari iOS' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'Safari, iOS', 'phone'];
        yield 'Chrome Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'Chrome, Windows', 'pc-display'];
        yield 'Edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36 Edg/141.0.0.0', 'Edge, Windows', 'pc-display'];
        yield 'Android tablet' => ['Mozilla/5.0 (Linux; Android 15; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', 'Chrome, Android', 'tablet'];
        yield 'empty' => ['', 'Неизвестное устройство', 'laptop'];
    }

    #[DataProvider('userAgents')]
    public function testUserAgentLabel(string $ua, string $label, string $icon): void
    {
        $agent = UserAgent::parse($ua);
        self::assertSame($label, $agent->label());
        self::assertSame($icon, $agent->icon);
    }

    public function testCspAddsSourcesToTheStrictBase(): void
    {
        self::assertStringNotContainsString('frame-src', Csp::header());
        $header = Csp::header(['script-src' => ['https://challenges.cloudflare.com'], 'frame-src' => ['https://challenges.cloudflare.com']]);
        self::assertStringContainsString("script-src 'self' https://challenges.cloudflare.com;", $header);
        self::assertStringContainsString('frame-src https://challenges.cloudflare.com;', $header);
        self::assertStringContainsString("frame-ancestors 'none'", $header);
    }

    public function testStubCaptchaWantsTheCheckbox(): void
    {
        $captcha = new StubCaptcha();
        self::assertFalse($captcha->verify(new Request('POST', '/login/')));
        self::assertTrue($captcha->verify(new Request('POST', '/login/', post: ['captcha_confirm' => '1'])));
    }

    public function testTurnstileAsksCloudflare(): void
    {
        $sent = [];
        $captcha = new TurnstileCaptcha('site', 'secret', new Logger('/dev/null'), static function (string $url, array $fields) use (&$sent): ?string {
            $sent = $fields + ['url' => $url];

            return json_encode(['success' => $fields['response'] === 'good']) ?: null;
        });
        $request = static fn(string $answer): Request => new Request('POST', '/login/', post: ['cf-turnstile-response' => $answer], server: ['REMOTE_ADDR' => '192.0.2.1']);

        self::assertTrue($captcha->verify($request('good')));
        self::assertSame(['secret' => 'secret', 'response' => 'good', 'remoteip' => '192.0.2.1', 'url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify'], $sent);
        self::assertFalse($captcha->verify($request('bad')));
        self::assertFalse($captcha->verify($request('')));
        self::assertSame(['https://challenges.cloudflare.com'], $captcha->cspSources()['frame-src'] ?? null);
    }

    public function testTurnstileFailsClosedWhenUnreachable(): void
    {
        $captcha = new TurnstileCaptcha('site', 'secret', new Logger('/dev/null'), static fn(string $url, array $fields): ?string => null);
        self::assertFalse($captcha->verify(new Request('POST', '/login/', post: ['cf-turnstile-response' => 'x'])));
    }

    public function testCaptchaProviderFromConfig(): void
    {
        $logger = new Logger('/dev/null');
        self::assertInstanceOf(StubCaptcha::class, CaptchaFactory::create(new Config(), $logger));
        $turnstile = new Config(['captcha' => ['provider' => 'turnstile', 'site_key' => 'a', 'secret_key' => 'b']]);
        self::assertInstanceOf(TurnstileCaptcha::class, CaptchaFactory::create($turnstile, $logger));
    }
}
