<?php

// PHPUnit bootstrap.
//
// Integration tests need an empty MariaDB database that they may wipe.
// They are skipped unless ESTABLES_TEST_DB_NAME is set; the other settings
// default to a local server:
//   ESTABLES_TEST_DB_HOST (127.0.0.1), ESTABLES_TEST_DB_PORT (3306),
//   ESTABLES_TEST_DB_SOCKET, ESTABLES_TEST_DB_USER (root), ESTABLES_TEST_DB_PASSWORD
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
