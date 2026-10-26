<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Catalog\RubricException;
use Estables\Catalog\RubricRepository;
use Estables\Core\Logger;
use Estables\Core\Services;

/**
 * Rubrics in the database: tree, addresses, moves, history.
 */
final class RubricRepositoryTest extends DatabaseTestCase
{
    private RubricRepository $rubrics;

    protected function setUp(): void
    {
        parent::setUp();
        $services = new Services($this->site->paths, $this->config, new Logger($this->site->paths->var() . '/log/estables.log'));
        $services->schema()->ensureCurrent();
        $this->rubrics = $services->rubrics();
    }

    public function testCreateBuildsSlugsPathsAndOrder(): void
    {
        $figures = $this->add('Фигурки');
        $porcelain = $this->add('Фарфор', $figures);
        $lfz = $this->add('Ленинградский завод', $porcelain, 'lfz');
        $dulevo = $this->add('Дулёвский завод', $porcelain);
        $twin = $this->add('Фарфор', $figures);

        $tree = $this->rubrics->tree();
        self::assertSame('figurki/farfor/lfz', $tree->slugPath($lfz));
        self::assertSame('/catalog/figurki/farfor/dulyovskiy-zavod/', $tree->url($dulevo));
        self::assertSame('farfor-2', $tree->node($twin)['slug'], 'A taken slug gets a suffix');
        self::assertSame([$lfz, $dulevo], $tree->children($porcelain));
        self::assertSame([$figures, $porcelain], $tree->ancestors($lfz));
        self::assertSame($lfz, $tree->findByPath('figurki/farfor/lfz'));
        self::assertNull($tree->findByPath('figurki/lfz'));
        self::assertSame("/{$figures}/{$porcelain}/{$lfz}/", $this->db->value('SELECT path FROM rubrics WHERE id = ' . $lfz));
        self::assertFileExists($this->site->paths->cache() . '/rubrics.ser', 'The tree is cached');
    }

    public function testValidation(): void
    {
        $figures = $this->add('Фигурки');
        $porcelain = $this->add('Фарфор', $figures);

        $errors = $this->rubrics->validate($this->input('', $figures, 'Bad Slug'));
        self::assertArrayHasKey('name', $errors);
        self::assertArrayHasKey('slug', $errors);
        self::assertSame('Такой адрес уже есть у соседнего раздела.', $this->rubrics->validate($this->input('Ещё фарфор', $figures, 'farfor'))['slug'] ?? null);
        self::assertSame([], $this->rubrics->validate($this->input('Фарфор', $figures, 'farfor'), $porcelain), 'Its own slug is fine');
        self::assertArrayHasKey('parent', $this->rubrics->validate($this->input('Фигурки', $porcelain), $figures), 'Not into its own subtree');
        self::assertArrayHasKey('parent', $this->rubrics->validate($this->input('Фигурки', 999)));
    }

    public function testRenamingRecordsFormerAddressesOfTheSubtree(): void
    {
        $figures = $this->add('Фигурки');
        $porcelain = $this->add('Фарфор', $figures);
        $lfz = $this->add('ЛФЗ', $porcelain, 'lfz');

        $this->rubrics->update($figures, $this->input('Фигуры', 0, 'figury'));
        $tree = $this->rubrics->tree();
        self::assertSame('figury/farfor/lfz', $tree->slugPath($lfz));
        self::assertSame($figures, $this->rubrics->formerAddress('figurki'));
        self::assertSame($lfz, $this->rubrics->formerAddress('figurki/farfor/lfz'));

        // Back to the old slug: the live address no longer redirects
        $this->rubrics->update($figures, $this->input('Фигурки', 0, 'figurki'));
        self::assertNull($this->rubrics->formerAddress('figurki'));
        self::assertSame($figures, $this->rubrics->formerAddress('figury'));
    }

    public function testMoveReordersAndReparents(): void
    {
        $a = $this->add('Альфа');
        $b = $this->add('Бета');
        $c = $this->add('Гамма');
        $child = $this->add('Дочерний', $a);

        $this->rubrics->move($c, 0, 0);
        self::assertSame([$c, $a, $b], $this->rubrics->tree()->children(0));

        $this->rubrics->move($a, $b, 0);
        $tree = $this->rubrics->tree();
        self::assertSame([$c, $b], $tree->children(0));
        self::assertSame([$a], $tree->children($b));
        self::assertSame('beta/alfa/docherniy', $tree->slugPath($child));
        self::assertSame("/{$b}/{$a}/{$child}/", $this->db->value('SELECT path FROM rubrics WHERE id = ' . $child));
        self::assertSame($child, $this->rubrics->formerAddress('alfa/docherniy'));

        try {
            $this->rubrics->move($b, $child, 0);
            self::fail('Moved into its own subtree');
        } catch (RubricException) {
        }
    }

    public function testMoveRefusesASlugClash(): void
    {
        $a = $this->add('Альфа');
        $b = $this->add('Бета');
        $this->add('Общий', $a, 'obshchiy');
        $other = $this->add('Общий', $b, 'obshchiy');
        $this->expectException(RubricException::class);
        $this->rubrics->move($other, $a, 0);
    }

    public function testDeleteOnlyWithoutSubrubrics(): void
    {
        $a = $this->add('Альфа');
        $child = $this->add('Дочерний', $a);
        try {
            $this->rubrics->delete($a);
            self::fail('Deleted a rubric with subrubrics');
        } catch (RubricException) {
        }
        $this->rubrics->update($child, $this->input('Дочерний', $a, 'novyy'));
        $this->rubrics->delete($child);
        self::assertNull($this->rubrics->formerAddress('alfa/docherniy'), 'History of a deleted rubric goes too');
        $this->rubrics->delete($a);
        self::assertTrue($this->rubrics->tree()->isEmpty());
    }

    public function testVisibilityOfAncestorsHidesTheSubtree(): void
    {
        $a = $this->add('Альфа');
        $child = $this->add('Дочерний', $a);
        self::assertTrue($this->rubrics->tree()->isShown($child));
        $this->rubrics->update($a, ['visible' => false] + $this->input('Альфа', 0, 'alfa'));
        self::assertFalse($this->rubrics->tree()->isShown($child));
    }

    private function add(string $name, int $parent = 0, string $slug = ''): int
    {
        $input = $this->input($name, $parent, $slug);
        self::assertSame([], $this->rubrics->validate($input));

        return $this->rubrics->create($input);
    }

    /**
     * @return array{parent: int, name: string, title: string, slug: string, description: string, visible: bool}
     */
    private function input(string $name, int $parent = 0, string $slug = ''): array
    {
        return ['parent' => $parent, 'name' => $name, 'title' => '', 'slug' => $slug, 'description' => '', 'visible' => true];
    }
}
