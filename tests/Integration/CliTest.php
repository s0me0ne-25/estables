<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Cli\Application;
use Estables\Core\Logger;
use Estables\Core\Services;

/**
 * Account commands of bin/estables.
 */
final class CliTest extends DatabaseTestCase
{
    private Services $services;

    protected function setUp(): void
    {
        parent::setUp();
        $this->services = new Services($this->site->paths, $this->config, new Logger($this->site->paths->var() . '/log/estables.log'));
        $this->services->schema()->ensureCurrent();
        $this->services->users()->create('keeper', 'correct horse battery', '');
    }

    public function testPasswdChangesThePasswordAndEndsSessions(): void
    {
        $user = $this->services->users()->findByLogin('keeper');
        self::assertNotNull($user);
        $this->services->sessions()->create($user, true, '192.0.2.1', 'test');

        [$code, $out] = $this->cli(['passwd', 'keeper', '--password-stdin'], "a brand new password\n");
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('все его сессии завершены', $out);
        self::assertTrue(password_verify('a brand new password', (string) $this->db->value('SELECT password_hash FROM users')));
        self::assertSame(0, (int) $this->db->value('SELECT COUNT(*) FROM sessions'));

        [$code, $out] = $this->cli(['passwd', 'keeper', '--password-stdin'], "short\n");
        self::assertSame(1, $code);
        self::assertStringContainsString('Не короче 12 символов', $out);

        [$code, $out] = $this->cli(['passwd', 'nobody', '--password-stdin'], "a brand new password\n");
        self::assertSame(1, $code);
        self::assertStringContainsString('нет', $out);
    }

    public function testUnlockForgetsFailedAttempts(): void
    {
        $throttle = $this->services->throttle();
        for ($i = 0; $i < 5; $i++) {
            $throttle->recordFailure('keeper', '192.0.2.' . $i);
        }
        self::assertGreaterThan(0, $throttle->lockedFor('keeper', '198.51.100.1'));

        [$code, $out] = $this->cli(['unlock', 'keeper']);
        self::assertSame(0, $code, $out);
        self::assertStringContainsString('сброшено: 5', $out);
        self::assertSame(0, $throttle->lockedFor('keeper', '198.51.100.1'));
    }

    /**
     * @param list<string> $argv
     *
     * @return array{int, string} exit code and everything printed
     */
    private function cli(array $argv, string $stdin = ''): array
    {
        $in = fopen('php://memory', 'r+');
        $out = fopen('php://memory', 'r+');
        self::assertIsResource($in);
        self::assertIsResource($out);
        fwrite($in, $stdin);
        rewind($in);
        $code = new Application($this->site->paths, $in, $out, $out)->run($argv);
        rewind($out);

        return [$code, (string) stream_get_contents($out)];
    }
}
