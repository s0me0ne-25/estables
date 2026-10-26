<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Unit;

use Estables\Core\Text;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    public function testPlural(): void
    {
        $forms = ['минута', 'минуты', 'минут'];
        $expected = [1 => '1 минута', 2 => '2 минуты', 5 => '5 минут', 11 => '11 минут', 12 => '12 минут', 21 => '21 минута', 24 => '24 минуты', 111 => '111 минут', 0 => '0 минут'];
        foreach ($expected as $n => $text) {
            self::assertSame($text, Text::plural($n, ...$forms));
        }
    }
}
