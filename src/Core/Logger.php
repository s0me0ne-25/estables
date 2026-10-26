<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

/**
 * Appends lines to var/log/estables.log; rotation is left to logrotate.
 * Falls back to the PHP error log if the file cannot be written.
 */
final class Logger
{
    public function __construct(private readonly string $file) {}

    public function info(string $message): void
    {
        $this->write('INFO', $message);
    }

    public function warning(string $message): void
    {
        $this->write('WARNING', $message);
    }

    public function error(string $message, ?\Throwable $e = null): void
    {
        if ($e !== null) {
            $message .= "\n" . $e::class . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString();
        }
        $this->write('ERROR', $message);
    }

    private function write(string $level, string $message): void
    {
        $line = \sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), $level, $message);
        $dir = \dirname($this->file);
        if ((is_dir($dir) || @mkdir($dir, 0o775, true)) && @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX) !== false) {
            return;
        }
        error_log('estables: ' . rtrim($line));
    }
}
