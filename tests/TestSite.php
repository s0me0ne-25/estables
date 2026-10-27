<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests;

use Estables\Core\Paths;

/**
 * A throw-away installation root: the real templates and public assets,
 * plus its own config/ and var/ directories.
 */
final class TestSite
{
    public readonly Paths $paths;

    public function __construct()
    {
        $root = sys_get_temp_dir() . '/estables-site-' . bin2hex(random_bytes(6));
        mkdir($root . '/config', 0o777, true);
        $repo = \dirname(__DIR__);
        symlink($repo . '/templates', $root . '/templates');
        // Its own public/ so that photos land in the throw-away site, with the real assets
        mkdir($root . '/public', 0o777, true);
        symlink($repo . '/public/assets', $root . '/public/assets');
        $this->paths = new Paths($root);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function writeConfig(array $config): void
    {
        file_put_contents($this->paths->configFile(), '<?php return ' . var_export($config, true) . ';');
    }

    public function remove(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->paths->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->paths->root);
    }
}
