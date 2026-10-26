<?php

// Coding style: PER Coding Style (successor of PSR-12) plus a few strictness rules.
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

$finder = new PhpCsFixer\Finder()
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/config', __DIR__ . '/public'])
    ->append([__DIR__ . '/bin/estables', __FILE__]);

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/var/cache/php-cs-fixer.cache')
    ->setRules([
        '@PER-CS' => true,
        '@PHP8x4Migration' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const']],
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'match', 'parameters']],
        'strict_comparison' => true,
        'strict_param' => true,
    ])
    ->setFinder($finder);
