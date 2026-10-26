<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Unit;

use Estables\Catalog\Slug;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    public function testFromText(): void
    {
        self::assertSame('figurki', Slug::fromText('Фигурки'));
        self::assertSame('leningradskiy-zavod', Slug::fromText('Ленинградский завод'));
        self::assertSame('khudozhestvennoe-lityo', Slug::fromText('Художественное литьё'));
        self::assertSame('medali-i-znaki', Slug::fromText('  Медали и знаки!  '));
        self::assertSame('orlovskie-rysaki-1950-e', Slug::fromText('Орловские рысаки, 1950-е'));
        self::assertSame('meissen-porzellan', Slug::fromText('Meißen Porzellan'));
        self::assertSame('', Slug::fromText('—'));
        self::assertSame(100, \strlen(Slug::fromText(str_repeat('щ', 40))), 'Cut to the maximum length');
    }

    public function testIsValid(): void
    {
        self::assertTrue(Slug::isValid('lfz'));
        self::assertTrue(Slug::isValid('a-1-b'));
        self::assertFalse(Slug::isValid(''));
        self::assertFalse(Slug::isValid('Lfz'));
        self::assertFalse(Slug::isValid('-lfz'));
        self::assertFalse(Slug::isValid('a--b'));
        self::assertFalse(Slug::isValid('a/b'));
        self::assertFalse(Slug::isValid(str_repeat('a', 101)));
    }
}
