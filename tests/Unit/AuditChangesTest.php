<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Unit;

use Estables\Audit\AuditLog;
use Estables\Audit\Changes;
use Estables\Catalog\RubricTree;
use Estables\Core\Logger;
use PHPUnit\Framework\TestCase;

final class AuditChangesTest extends TestCase
{
    private const ITEM = [
        'catno' => 'ЛФЗ-1042', 'name' => 'Конь', 'summary' => '', 'description' => '', 'yearFrom' => '1958', 'yearTo' => '',
        'published' => true, 'rubrics' => [1, 2], 'main' => 1, 'attributes' => [], 'variants' => [],
    ];

    public function testPhotoChanges(): void
    {
        $tree = new RubricTree([]);
        $a = ['sha' => 'a', 'caption' => ''];
        $b = ['sha' => 'b', 'caption' => ''];
        $c = ['sha' => 'c', 'caption' => 'Клеймо'];
        self::assertSame('', Changes::item(self::ITEM, self::ITEM, [$a, $b, $c], [$a, $b, $c], $tree));
        self::assertSame('порядок фото', Changes::item(self::ITEM, self::ITEM, [$a, $b], [$b, $a], $tree));
        self::assertSame('убрано 1 фото, обложка', Changes::item(self::ITEM, self::ITEM, [$a, $b], [$b], $tree));
        self::assertSame('подписи к фото', Changes::item(self::ITEM, self::ITEM, [$c], [['sha' => 'c', 'caption' => '']], $tree));
    }

    public function testItemFields(): void
    {
        $tree = new RubricTree([]);
        $after = ['main' => 2, 'rubrics' => [2, 1], 'yearTo' => '1979', 'published' => false] + self::ITEM;
        self::assertSame('снят с публикации; годы выпуска; главный раздел №2', Changes::item(self::ITEM, $after, [], [], $tree));
    }

    public function testLinksOnlyToObjectsWithPages(): void
    {
        self::assertSame('/admin/items/5/', AuditLog::link('item.update', 5));
        self::assertSame('/admin/rubrics/?id=3', AuditLog::link('rubric.move', 3));
        self::assertNull(AuditLog::link('item.delete', 5));
        self::assertNull(AuditLog::link('items.publish', null));
    }

    public function testLogQuoting(): void
    {
        self::assertSame('"a\\"b\\\\c\\x0ad\\x09é"', Logger::quote("a\"b\\c\nd\té"));
        self::assertSame('"?"', Logger::quote("\xff"), 'Broken UTF-8 is replaced');
    }
}
