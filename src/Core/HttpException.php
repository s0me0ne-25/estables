<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Core;

/**
 * Ends request handling with an HTTP error page (404, 405, 400...).
 */
final class HttpException extends \RuntimeException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        string $message = '',
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function notFound(): self
    {
        return new self(404, 'Not Found');
    }
}
