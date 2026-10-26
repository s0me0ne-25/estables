<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Db;

/**
 * Result of the schema check performed on a request.
 */
enum SchemaState
{
    /** The schema matches the code. */
    case Current;

    /** Another process is upgrading the schema right now. */
    case Busy;

    /** The database is newer than the code (files were rolled back). */
    case DatabaseNewer;
}
