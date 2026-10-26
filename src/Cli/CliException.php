<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Cli;

/**
 * A command cannot run; the message is printed for the operator.
 */
final class CliException extends \RuntimeException {}
