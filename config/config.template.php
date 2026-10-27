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

    // Login sessions.
    'session' => [
        // "Remember me": lifetime of the cookie and of the session, renewed on every visit.
        'remember_days' => 30,
        // Without "remember me" the cookie lives until the browser is closed;
        // the server also ends the session after this much inactivity.
        'idle_hours' => 12,
        // Activity is written to the database at most this often.
        'touch_minutes' => 5,
        // The session identifier is replaced this often.
        'rotate_hours' => 24,
    ],

    // Brute-force protection of the login form. Failures are counted over
    // lockout_minutes; reaching a limit blocks the form for that login or IP.
    // Every failure is logged to var/log/estables.log for fail2ban.
    'login' => [
        'max_failures_per_login' => 5,
        'max_failures_per_ip' => 20,
        'lockout_minutes' => 15,
    ],

    // Captcha on the login form.
    //   'stub'      — an "I am human" checkbox without a real check (development,
    //                 or when Anubis / fail2ban protect the site);
    //   'turnstile' — Cloudflare Turnstile, keys from the Cloudflare dashboard.
    'captcha' => [
        'provider' => 'stub',
        'site_key' => '',
        'secret_key' => '',
    ],

    // Photos. Originals are kept cleaned of metadata in public/media/originals/;
    // smaller copies are made on the first request and kept next to them.
    'media' => [
        // Limits of an uploaded file; the size must also fit php-fpm's
        // upload_max_filesize and nginx's client_max_body_size.
        'max_upload_mb' => 50,
        'max_megapixels' => 100,
        // Longest side of the copies: cards and lists, and the item page.
        'thumb_size' => 400,
        'large_size' => 1600,
        // An original up to this size is shown itself instead of the large copy.
        'original_max' => 2560,
        // Lossless JPEG cleaning and rotation (package libjpeg-turbo-progs).
        'jpegtran' => 'jpegtran',
        // Memory ImageMagick may use before it spills to disk.
        'imagick_memory_mb' => 256,
        'imagick_map_mb' => 1024,
        // bin/estables gc deletes images nobody uses after this delay.
        'gc_delay_hours' => 24,
    ],
];
