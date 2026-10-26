<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Unit;

use Estables\Core\HttpException;
use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Core\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $echo = static fn(Request $r, array $p): Response => Response::text(json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        $this->router->add('home', 'GET', '/', $echo);
        $this->router->add('item', 'GET', '/item/{slug}/', $echo);
        $this->router->add('rubric', 'GET', '/catalog/{path:.+}/', $echo);
        $this->router->add('setup', ['GET', 'POST'], '/setup/', $echo);
    }

    public function testMatchesStaticAndPlaceholderRoutes(): void
    {
        self::assertSame('[]', $this->router->dispatch(new Request('GET', '/'))->body());
        self::assertSame('{"slug":"lfz-1042"}', $this->router->dispatch(new Request('GET', '/item/lfz-1042/'))->body());
        self::assertSame(
            '{"path":"figurki/farfor"}',
            $this->router->dispatch(new Request('GET', '/catalog/figurki/farfor/'))->body(),
        );
    }

    public function testDecodesParameters(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/item/' . rawurlencode('лфз-1042') . '/'));
        self::assertSame('{"slug":"лфз-1042"}', $response->body());
    }

    public function testHeadIsAllowedWhereGetIs(): void
    {
        self::assertSame(200, $this->router->dispatch(new Request('HEAD', '/'))->status());
    }

    public function testRedirectsToTheCanonicalSlashForm(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/item/lfz-1042', ['a' => 'b']));
        self::assertSame(301, $response->status());
        self::assertSame('/item/lfz-1042/?a=b', $response->header('Location'));
    }

    public function testUnknownPathIs404(): void
    {
        $this->expectExceptionObject(HttpException::notFound());
        $this->router->dispatch(new Request('GET', '/nope/'));
    }

    public function testWrongMethodIs405WithAllowHeader(): void
    {
        try {
            $this->router->dispatch(new Request('DELETE', '/setup/'));
            self::fail('405 expected');
        } catch (HttpException $e) {
            self::assertSame(405, $e->status);
            self::assertSame('GET, POST, HEAD', $e->headers['Allow']);
        }
    }

    public function testBuildsUrls(): void
    {
        self::assertSame('/', $this->router->url('home'));
        self::assertSame('/item/lfz-1042/', $this->router->url('item', ['slug' => 'lfz-1042']));
        self::assertSame('/catalog/a%20b/c/', $this->router->url('rubric', ['path' => 'a b/c']));
    }

    public function testUrlNeedsAllParameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->router->url('item');
    }
}
