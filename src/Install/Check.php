<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Install;

/**
 * One line of the environment check.
 */
final readonly class Check
{
    public const OK = 'ok';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    /**
     * @param string $hint what to do when the check is not OK
     */
    public function __construct(
        public string $title,
        public string $status,
        public string $hint,
    ) {}

    public function ok(): bool
    {
        return $this->status === self::OK;
    }
}
