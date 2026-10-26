<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Install;

use Estables\Auth\UserRepository;
use Estables\Core\Paths;

/**
 * Whether the first administrator exists, and the one-time token that guards
 * the web form for creating them.
 *
 * "Installed" is cached in var/cache/installed so that ordinary requests do
 * not query the users table.
 */
final class InstallState
{
    public function __construct(
        private readonly Paths $paths,
        private readonly UserRepository $users,
    ) {}

    public function isInstalled(): bool
    {
        if (is_file($this->flagFile())) {
            return true;
        }
        if ($this->users->count() > 0) {
            $this->markInstalled();

            return true;
        }

        return false;
    }

    public function markInstalled(): void
    {
        $this->paths->writeAtomic($this->flagFile(), gmdate('c'));
        @unlink($this->paths->installToken());
    }

    /**
     * Creates the install token file if it does not exist yet.
     */
    public function ensureToken(): void
    {
        $file = $this->paths->installToken();
        if (!is_file($file)) {
            $this->paths->writeAtomic($file, bin2hex(random_bytes(16)) . "\n", 0o640);
        }
    }

    public function tokenMatches(string $token): bool
    {
        $file = $this->paths->installToken();
        if ($token === '' || !is_file($file)) {
            return false;
        }

        return hash_equals(trim((string) file_get_contents($file)), trim($token));
    }

    private function flagFile(): string
    {
        return $this->paths->var() . '/cache/installed';
    }
}
