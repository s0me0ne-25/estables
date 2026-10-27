<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

use Estables\Audit\AuditLog;
use Estables\Auth\Captcha\Captcha;
use Estables\Auth\Captcha\CaptchaFactory;
use Estables\Auth\Csrf;
use Estables\Auth\LoginThrottle;
use Estables\Auth\SessionStore;
use Estables\Auth\UserRepository;
use Estables\Catalog\ItemRepository;
use Estables\Catalog\RubricRepository;
use Estables\Db\Backup;
use Estables\Db\Connection;
use Estables\Db\SchemaManager;
use Estables\Install\InstallState;
use Estables\Media\ImageRepository;
use Estables\Media\Ingest;
use Estables\Media\MediaStore;
use Estables\Media\Thumbnailer;

/**
 * Lazily built services of a configured installation, shared by the web
 * kernel and the command line.
 */
final class Services
{
    private ?Connection $db = null;
    private ?UserRepository $users = null;
    private ?RubricRepository $rubrics = null;
    private ?ItemRepository $items = null;
    private ?AuditLog $audit = null;

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

    public function rubrics(): RubricRepository
    {
        return $this->rubrics ??= new RubricRepository($this->db(), $this->paths);
    }

    public function items(): ItemRepository
    {
        return $this->items ??= new ItemRepository($this->db(), $this->rubrics());
    }

    public function media(): MediaStore
    {
        return new MediaStore($this->paths, $this->config);
    }

    public function images(): ImageRepository
    {
        return new ImageRepository($this->db());
    }

    public function ingest(): Ingest
    {
        return new Ingest($this->db(), $this->media(), $this->config, $this->logger);
    }

    public function thumbnailer(): Thumbnailer
    {
        return new Thumbnailer($this->media(), $this->config, $this->paths);
    }

    public function sessions(): SessionStore
    {
        return new SessionStore($this->db(), $this->config);
    }

    public function throttle(): LoginThrottle
    {
        return new LoginThrottle($this->db(), $this->config);
    }

    public function captcha(): Captcha
    {
        return CaptchaFactory::create($this->config, $this->logger);
    }

    public function csrf(): Csrf
    {
        return new Csrf($this->config->string('app.secret'));
    }

    /**
     * Shared, so the actor set by the kernel applies to every entry of the request.
     */
    public function audit(): AuditLog
    {
        return $this->audit ??= new AuditLog($this->db(), $this->logger);
    }

    public function installState(): InstallState
    {
        return new InstallState($this->paths, $this->users());
    }
}
