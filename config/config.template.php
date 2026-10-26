<?php

// eStables configuration template.
//
// Copy this file to config/config.php (ignored by git) and fill it in:
//   cp config/config.template.php config/config.php
// Settings left out of config.php fall back to built-in defaults, so the file
// keeps working when new builds add settings.
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

return [
    'app' => [
        // Public address of the site without a trailing slash.
        'base_url' => 'https://example.org',

        // Random secret for signing forms and service tokens, at least 32 characters.
        // Generate one with: php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
        // Changing it later invalidates open forms; keep it private.
        'secret' => '',

        // Show error details on the page. Never enable on a public site.
        'debug' => false,

        // Time zone for dates shown on the site (stored dates are UTC).
        'timezone' => 'Europe/Moscow',

        // Link to the source code in the footer (required by the AGPL).
        'source_url' => 'https://github.com/s0me0ne-25/estables',
    ],

    // MariaDB connection; the database must be empty on the first run.
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        // Unix socket path; when set, host and port are ignored.
        // Debian: /run/mysqld/mysqld.sock
        'socket' => '',
        'name' => 'estables',
        'user' => 'estables',
        'password' => '',
    ],

    // Database dump before every schema upgrade, into var/backups/.
    'backup' => [
        'enabled' => true,
        'dump_binary' => 'mariadb-dump',
        // Number of dumps to keep.
        'keep' => 10,
    ],
];
