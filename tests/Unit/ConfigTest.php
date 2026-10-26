<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Unit;

use Estables\Core\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testMergesOverDefaults(): void
    {
        $config = new Config(['db' => ['name' => 'catalogue']]);
        self::assertSame('catalogue', $config->string('db.name'));
        self::assertSame(3306, $config->int('db.port'));
        self::assertTrue($config->bool('backup.enabled'));
        self::assertSame('fallback', $config->get('no.such.key', 'fallback'));
    }

    public function testValidConfigHasNoProblems(): void
    {
        self::assertSame([], $this->valid()->problems());
    }

    public function testReportsProblems(): void
    {
        $config = new Config([
            'app' => ['secret' => 'short', 'base_url' => 'https://example.org/', 'timezone' => 'Mars/Olympus'],
        ]);
        $problems = implode("\n", $config->problems());
        self::assertStringContainsString('app.secret', $problems);
        self::assertStringContainsString('app.base_url', $problems);
        self::assertStringContainsString('app.timezone', $problems);
    }

    public function testLoadReturnsNullWithoutFile(): void
    {
        self::assertNull(Config::load(sys_get_temp_dir() . '/no-such-estables-config.php'));
    }

    private function valid(): Config
    {
        return new Config([
            'app' => ['secret' => str_repeat('a', 64), 'base_url' => 'https://example.org', 'timezone' => 'Europe/Moscow'],
        ]);
    }
}
