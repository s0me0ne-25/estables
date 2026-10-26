<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

use Estables\Auth\UserRepository;
use Estables\Db\Backup;
use Estables\Db\Connection;
use Estables\Db\SchemaManager;
use Estables\Install\InstallState;

/**
 * Lazily built services of a configured installation, shared by the web
 * kernel and the command line.
 */
final class Services
{
    private ?Connection $db = null;
    private ?UserRepository $users = null;

    public function __construct(
        public readonly Paths $paths,
        public readonly Config $config,
        public readonly Logger $logger,
    ) {}

    public static function logger(Paths $paths): Logger
    {
        return new Logger($paths->var() . '/log/estables.log');
    }

    public function db(): Connection
    {
        return $this->db ??= new Connection($this->config);
    }

    public function schema(): SchemaManager
    {
        return new SchemaManager($this->db(), $this->paths, new Backup($this->config, $this->paths), $this->logger);
    }

    public function users(): UserRepository
    {
        return $this->users ??= new UserRepository($this->db());
    }

    public function installState(): InstallState
    {
        return new InstallState($this->paths, $this->users());
    }
}
