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

    /**
     * A value typed by someone, safe inside a log line: in double quotes, with
     * quotes, backslashes and line breaks escaped, so it can neither end the
     * quotes nor forge a line of its own (fail2ban reads these lines).
     */
    public static function quote(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');

        return '"' . preg_replace_callback(
            '~[\x00-\x1f\x7f"\\\\]~',
            static fn(array $m): string => match ($m[0]) {
                '"' => '\\"',
                '\\' => '\\\\',
                default => \sprintf('\\x%02x', \ord($m[0])),
            },
            $value,
        ) . '"';
    }

    private function write(string $level, string $message): void
    {
        // With the UTC offset: fail2ban compares the time with its own clock,
        // and app.timezone may differ from the server's.
        $line = \sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s O'), $level, $message);
        $dir = \dirname($this->file);
        if ((is_dir($dir) || @mkdir($dir, 0o775, true)) && @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX) !== false) {
            return;
        }
        error_log('estables: ' . rtrim($line));
    }
}
