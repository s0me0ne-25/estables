<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Unit;

use Estables\Core\Kernel;
use Estables\Core\Request;
use Estables\Tests\TestSite;
use PHPUnit\Framework\TestCase;

/**
 * Kernel behaviour that needs no database.
 */
final class KernelTest extends TestCase
{
    private TestSite $site;

    protected function setUp(): void
    {
        $this->site = new TestSite();
    }

    protected function tearDown(): void
    {
        $this->site->remove();
    }

    public function testMissingConfigShowsInstallInstructions(): void
    {
        $response = new Kernel($this->site->paths)->handle(new Request('GET', '/'));
        self::assertSame(503, $response->status());
        self::assertStringContainsString('Установка eStables', $response->body());
        self::assertStringContainsString('config.template.php', $response->body());
        self::assertSame('no-store', $response->header('Cache-Control'));
        self::assertStringContainsString("frame-ancestors 'none'", (string) $response->header('Content-Security-Policy'));
    }

    public function testInvalidConfigListsProblems(): void
    {
        $this->site->writeConfig(['app' => ['secret' => 'short']]);
        $response = new Kernel($this->site->paths)->handle(new Request('GET', '/'));
        self::assertSame(503, $response->status());
        self::assertStringContainsString('app.secret', $response->body());
    }

    public function testUnreachableDatabaseIs503(): void
    {
        $this->site->writeConfig([
            'app' => ['secret' => str_repeat('s', 64)],
            'db' => ['host' => '127.0.0.1', 'port' => 1, 'name' => 'x', 'user' => 'x'],
        ]);
        $response = new Kernel($this->site->paths)->handle(new Request('GET', '/'));
        self::assertSame(503, $response->status());
        self::assertStringContainsString('Нет связи с базой данных', $response->body());
    }
}
