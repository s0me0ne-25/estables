<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

/**
 * Turns PHP warnings and notices into exceptions and logs fatal errors that
 * escape normal handling.
 */
final class ErrorHandler
{
    public function __construct(private readonly Logger $logger) {}

    public function register(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false; // silenced with @
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error === null || !\in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            $this->logger->error("Fatal error: {$error['message']} in {$error['file']}:{$error['line']}");
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=utf-8');
                echo "Ошибка на сервере. Подробности записаны в журнал.\n";
            }
        });
    }
}
