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
 *
 * Dumps before schema upgrades and daily dumps are kept separately
 * (backup.keep and backup.keep_daily), so a run of daily dumps never
 * pushes out the copy made before an upgrade.
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
     * The dump before a schema upgrade to the given version.
     *
     * @throws \RuntimeException when the dump fails
     */
    public function beforeUpgrade(int $version): string
    {
        $file = $this->create('before-v' . $version);
        $this->prune('-before-v*.sql.gz', $this->config->int('backup.keep'));

        return $file;
    }

    /**
     * The daily dump (bin/estables backup).
     *
     * @throws \RuntimeException when the dump fails
     */
    public function daily(): string
    {
        $file = $this->create('daily');
        $this->prune('-daily.sql.gz', $this->config->int('backup.keep_daily'));

        return $file;
    }

    /**
     * Dumps the whole database; returns the path of the .sql.gz file.
     *
     * @throws \RuntimeException when the dump fails
     */
    private function create(string $label): string
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
     * Keeps the newest $keep dumps whose names end with $suffix (a glob).
     */
    private function prune(string $suffix, int $keep): void
    {
        $files = glob($this->paths->backups() . '/estables-*' . $suffix) ?: [];
        rsort($files);
        foreach (\array_slice($files, max(1, $keep)) as $old) {
            @unlink($old);
        }
    }
}
