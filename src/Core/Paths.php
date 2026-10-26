<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

/**
 * Filesystem layout of an installation. Everything is derived from the
 * project root; runtime directories under var/ are created on demand.
 */
final readonly class Paths
{
    public string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
    }

    public function configFile(): string
    {
        return $this->root . '/config/config.php';
    }

    public function configTemplate(): string
    {
        return $this->root . '/config/config.template.php';
    }

    public function templates(): string
    {
        return $this->root . '/templates';
    }

    public function public(): string
    {
        return $this->root . '/public';
    }

    public function var(): string
    {
        return $this->root . '/var';
    }

    public function cache(): string
    {
        return $this->ensureDir($this->var() . '/cache');
    }

    public function log(): string
    {
        return $this->ensureDir($this->var() . '/log');
    }

    public function backups(): string
    {
        return $this->ensureDir($this->var() . '/backups');
    }

    public function installToken(): string
    {
        return $this->var() . '/install.token';
    }

    /**
     * Creates the directory if needed and returns it.
     */
    public function ensureDir(string $dir): string
    {
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory {$dir}");
        }

        return $dir;
    }

    /**
     * Writes a file atomically: other processes see either the old or the new content.
     */
    public function writeAtomic(string $file, string $content, int $mode = 0o664): void
    {
        $this->ensureDir(\dirname($file));
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $content) === false) {
            throw new \RuntimeException("Cannot write {$tmp}");
        }
        chmod($tmp, $mode);
        if (!rename($tmp, $file)) {
            @unlink($tmp);

            throw new \RuntimeException("Cannot replace {$file}");
        }
    }
}
