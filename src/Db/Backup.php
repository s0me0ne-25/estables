<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Db;

use Estables\Core\Config;
use Estables\Core\Paths;

/**
 * Database dump with mariadb-dump into var/backups/, gzip-compressed.
 * Credentials go through a temporary option file, never the command line,
 * so they do not show up in the process list.
 */
final class Backup
{
    public function __construct(
        private readonly Config $config,
        private readonly Paths $paths,
    ) {}

    public function enabled(): bool
    {
        return $this->config->bool('backup.enabled');
    }

    /**
     * Dumps the whole database; returns the path of the .sql.gz file.
     *
     * @throws \RuntimeException when the dump fails
     */
    public function create(string $label): string
    {
        $dir = $this->paths->backups();
        $base = $dir . '/estables-' . date('Ymd-His') . '-' . preg_replace('~[^a-z0-9-]+~', '-', strtolower($label));
        $sqlFile = $base . '.sql';
        $optionFile = $this->writeOptionFile();

        try {
            $command = [
                $this->config->string('backup.dump_binary'),
                '--defaults-extra-file=' . $optionFile,
                '--single-transaction',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                '--result-file=' . $sqlFile,
                $this->config->string('db.name'),
            ];
            $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!\is_resource($process)) {
                throw new \RuntimeException('Cannot start ' . $command[0]);
            }
            stream_get_contents($pipes[1]);
            $errors = trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            if ($exitCode !== 0 || !is_file($sqlFile)) {
                throw new \RuntimeException("{$command[0]} failed with code {$exitCode}: {$errors}");
            }
        } finally {
            @unlink($optionFile);
        }

        $gzFile = $sqlFile . '.gz';
        $this->compress($sqlFile, $gzFile);
        $this->prune();

        return $gzFile;
    }

    private function writeOptionFile(): string
    {
        $lines = ['[client]', 'user=' . $this->quote($this->config->string('db.user')),
            'password=' . $this->quote($this->config->string('db.password'))];
        $socket = $this->config->string('db.socket');
        if ($socket !== '') {
            $lines[] = 'socket=' . $this->quote($socket);
        } else {
            $lines[] = 'host=' . $this->quote($this->config->string('db.host'));
            $lines[] = 'port=' . $this->config->int('db.port');
        }
        $file = $this->paths->ensureDir($this->paths->var() . '/tmp') . '/dump-' . bin2hex(random_bytes(8)) . '.cnf';
        $old = umask(0o077);
        try {
            if (file_put_contents($file, implode("\n", $lines) . "\n") === false) {
                throw new \RuntimeException('Cannot write the temporary option file');
            }
        } finally {
            umask($old);
        }

        return $file;
    }

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, '"\\') . '"';
    }

    private function compress(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($target, 'wb6');
        if ($in === false || $out === false) {
            throw new \RuntimeException('Cannot compress ' . $source);
        }
        while (!feof($in)) {
            $chunk = fread($in, 1 << 20);
            if ($chunk === false) {
                break;
            }
            gzwrite($out, $chunk);
        }
        fclose($in);
        gzclose($out);
        unlink($source);
        // Dumps contain password hashes.
        chmod($target, 0o600);
    }

    /**
     * Keeps the newest "backup.keep" dumps.
     */
    private function prune(): void
    {
        $files = glob($this->paths->backups() . '/estables-*.sql.gz') ?: [];
        rsort($files);
        foreach (\array_slice($files, max(1, $this->config->int('backup.keep'))) as $old) {
            @unlink($old);
        }
    }
}
