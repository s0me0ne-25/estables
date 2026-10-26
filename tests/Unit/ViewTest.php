<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Unit;

use Estables\Core\View;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/estables-view-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/layouts', 0o777, true);
        file_put_contents($this->dir . '/layouts/site.php', '<main><?= $content ?></main>');
        file_put_contents($this->dir . '/page.php', '<h1><?= $this->e($title) ?></h1><?= $this->e($site) ?>');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/{,*/}*.php', GLOB_BRACE) ?: []);
        rmdir($this->dir . '/layouts');
        rmdir($this->dir);
    }

    public function testEscapesAndWrapsIntoLayout(): void
    {
        $view = new View($this->dir, $this->dir, null, ['site' => 'eStables']);
        self::assertSame(
            '<main><h1>&lt;b&gt;&quot;Кони&quot; &amp; всадники&lt;/b&gt;</h1>eStables</main>',
            $view->page('page', ['title' => '<b>"Кони" & всадники</b>']),
        );
    }

    public function testAssetUrlCarriesVersion(): void
    {
        mkdir($this->dir . '/assets');
        file_put_contents($this->dir . '/assets/a.css', 'x');
        $view = new View($this->dir, $this->dir);
        self::assertMatchesRegularExpression('~^/assets/a\.css\?v=\d+$~', $view->asset('a.css'));
        unlink($this->dir . '/assets/a.css');
        rmdir($this->dir . '/assets');
    }
}
