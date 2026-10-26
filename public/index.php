<?php

// Front controller: every request that is not a static file ends up here.
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

$root = dirname(__DIR__);
if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "eStables: run \"composer install --no-dev\" in {$root} first.\n";

    return;
}
require $root . '/vendor/autoload.php';

new Estables\Core\Kernel(new Estables\Core\Paths($root))->run();
