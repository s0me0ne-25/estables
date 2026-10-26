<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Tests\Integration;

use Estables\Catalog\ItemRepository;
use Estables\Catalog\RubricRepository;
use Estables\Core\Logger;
use Estables\Core\Services;

/**
 * Items in the database: numbers, rubrics, authorial order, aggregates, search.
 */
final class ItemRepositoryTest extends DatabaseTestCase
{
    private ItemRepository $items;
    private RubricRepository $rubrics;
    private int $figures;
    private int $porcelain;
    private int $sets;

    protected function setUp(): void
    {
        parent::setUp();
        $services = new Services($this->site->paths, $this->config, new Logger($this->site->paths->var() . '/log/estables.log'));
        $services->schema()->ensureCurrent();
        $this->rubrics = $services->rubrics();
        $this->items = $services->items();
        $this->figures = $this->rubric('Фигурки');
        $this->porcelain = $this->rubric('Фарфор', $this->figures);
        $this->sets = $this->rubric('Подборки');
    }

    public function testCreateWithNumberRubricsAttributesAndVariants(): void
    {
        $id = $this->item('ЛФЗ-1042', 'Конь вздыбленный', [$this->porcelain, $this->sets], [
            'yearFrom' => '1958', 'yearTo' => '1979',
            'attributes' => [['name' => 'Материал', 'value' => 'Фарфор, бисквит'], ['name' => 'Высота', 'value' => '21 см']],
            'variants' => [['number' => '1042-a', 'finish' => 'Белый бисквит', 'years' => '1958–1965']],
        ]);
        $item = $this->items->find($id);
        self::assertNotNull($item);
        self::assertSame('lfz-1042', $item->slug);
        self::assertSame('/item/lfz-1042/', $item->url());
        self::assertSame('1958–1979', $item->years());
        self::assertSame([$this->porcelain, $this->sets], $this->items->rubricsOf($id));
        self::assertSame('Высота', $this->items->attributes($id)[1]['name']);
        self::assertSame('1042-a', $this->items->variants($id)[0]['number']);
        self::assertSame(['Высота', 'Материал'], $this->items->attributeNames());

        $auto = $this->item('', 'Без номера', [$this->porcelain]);
        self::assertSame(str_pad((string) $auto, 5, '0', STR_PAD_LEFT), $this->items->find($auto)?->catno);

        self::assertArrayHasKey('catno', $this->items->validate($this->input('лфз-1042', 'Двойник', [$this->sets])), 'Numbers are unique regardless of case');
        self::assertArrayHasKey('rubrics', $this->items->validate($this->input('X', 'Без разделов', [])));
        self::assertArrayHasKey('yearTo', $this->items->validate(['yearFrom' => '1990', 'yearTo' => '1980'] + $this->input('Y', 'Годы', [$this->sets])));
    }

    public function testNewNumberKeepsTheOldAddress(): void
    {
        $id = $this->item('ЛФЗ-1042', 'Конь', [$this->porcelain]);
        $this->items->save($id, $this->input('ЛФЗ-1043', 'Конь', [$this->porcelain]), null);
        self::assertSame('lfz-1043', $this->items->find($id)?->slug);
        self::assertSame($id, $this->items->formerAddress('lfz-1042'));
    }

    public function testNewItemsGoToTheTopAndMovesKeepTheOrder(): void
    {
        $a = $this->item('A-1', 'Альфа', [$this->porcelain]);
        $b = $this->item('A-2', 'Бета', [$this->porcelain]);
        $c = $this->item('A-3', 'Гамма', [$this->porcelain]);
        self::assertSame([$c, $b, $a], $this->order($this->porcelain));

        $this->items->moveInRubric($this->porcelain, $c, $a, null);
        self::assertSame([$b, $a, $c], $this->order($this->porcelain));
        $this->items->moveInRubric($this->porcelain, $a, null, $b);
        self::assertSame([$a, $b, $c], $this->order($this->porcelain));

        // Many moves into the same gap force a renumbering, the order survives
        for ($i = 0; $i < 12; $i++) {
            $this->items->moveInRubric($this->porcelain, $i % 2 === 0 ? $c : $b, $a, null);
        }
        self::assertSame([$a, $b, $c], $this->order($this->porcelain));

        $this->items->toEdge($this->porcelain, [$c, $b], true);
        self::assertSame([$b, $c, $a], $this->order($this->porcelain));
        $this->items->toEdge($this->porcelain, [$b], false);
        self::assertSame([$c, $a, $b], $this->order($this->porcelain));
        $this->items->toPosition($this->porcelain, [$b, $c], 2);
        self::assertSame([$a, $c, $b], $this->order($this->porcelain));
    }

    public function testRubricLinksMainAndRemoval(): void
    {
        $a = $this->item('A-1', 'Альфа', [$this->porcelain]);
        $b = $this->item('A-2', 'Бета', [$this->porcelain]);
        self::assertSame(2, $this->items->addToRubric([$a, $b, $a], $this->sets));
        self::assertSame([$a, $b], $this->order($this->sets), 'Added in the given order, on top');
        self::assertSame($this->porcelain, $this->items->rubricsOf($a)[0], 'The main rubric does not change');

        $result = $this->items->removeFromRubric([$a], $this->porcelain);
        self::assertSame(['removed' => 1, 'kept' => 0], $result);
        self::assertSame([$this->sets], $this->items->rubricsOf($a), 'The next rubric becomes the main one');
        self::assertSame(['removed' => 0, 'kept' => 1], $this->items->removeFromRubric([$a], $this->sets), 'The last rubric stays');
    }

    public function testReleaseRubricMovesOrDeletesItsOwnItems(): void
    {
        $only = $this->item('A-1', 'Только здесь', [$this->porcelain]);
        $shared = $this->item('A-2', 'Ещё и в подборке', [$this->porcelain, $this->sets]);
        $result = $this->items->release($this->porcelain, $this->figures);
        self::assertSame(['moved' => 1, 'deleted' => 0, 'unlinked' => 1], $result);
        self::assertSame([$this->figures], $this->items->rubricsOf($only));
        self::assertSame([$this->sets], $this->items->rubricsOf($shared));
        $this->rubrics->delete($this->porcelain);

        $result = $this->items->release($this->figures, null);
        self::assertSame(['moved' => 0, 'deleted' => 1, 'unlinked' => 0], $result);
        self::assertNull($this->items->find($only));
    }

    public function testTreeAggregatesCountDistinctPublishedItems(): void
    {
        $this->item('A-1', 'Альфа', [$this->porcelain, $this->figures], ['yearFrom' => '1958', 'yearTo' => '1979',
            'variants' => [['number' => 'a', 'finish' => '', 'years' => ''], ['number' => 'b', 'finish' => '', 'years' => '']]]);
        $this->item('A-2', 'Бета', [$this->porcelain], ['yearFrom' => '1985']);
        $this->item('A-3', 'Черновик', [$this->porcelain], ['published' => false]);
        $tree = $this->rubrics->tree();
        $figures = $tree->node($this->figures);
        self::assertSame(2, $figures['count'], 'An item in a rubric and its parent counts once');
        self::assertSame(2, $figures['variants']);
        self::assertSame(1958, $figures['yearMin']);
        self::assertSame(1985, $figures['yearMax']);
        self::assertSame(0, $tree->node($this->sets)['count']);

        $this->rubrics->update($this->porcelain, ['parent' => $this->figures, 'name' => 'Фарфор', 'title' => '', 'slug' => 'farfor', 'description' => '', 'visible' => false]);
        $tree = $this->rubrics->tree();
        self::assertSame(2, $tree->node($this->porcelain)['count'], 'A hidden rubric counts its own items');
        self::assertSame(1, $tree->node($this->figures)['count'], 'but they do not reach its parent');
    }

    public function testPublicListsDecadesNeighboursAndSearch(): void
    {
        $a = $this->item('ЛФЗ-998', 'Тройка', [$this->porcelain], ['yearFrom' => '1955']);
        $b = $this->item('ЛФЗ-1042', 'Конь вздыбленный', [$this->porcelain], ['yearFrom' => '1958',
            'attributes' => [['name' => 'Материал', 'value' => 'бисквит']]]);
        $c = $this->item('ЛФЗ-1051', 'Орловский рысак', [$this->porcelain], ['yearFrom' => '1962', 'summary' => 'Рысак на ходу']);
        $this->item('ЛФЗ-2000', 'Черновик', [$this->porcelain], ['published' => false]);

        $ids = static fn(array $list): array => array_column($list['rows'], 'id');
        self::assertSame([$c, $b, $a], $ids($this->items->rubricItems($this->porcelain, 'author', null, 1, 24)));
        self::assertSame([$a, $b, $c], $ids($this->items->rubricItems($this->porcelain, 'number', null, 1, 24)), 'Numbers compare by value');
        self::assertSame([$b, $c, $a], $ids($this->items->rubricItems($this->porcelain, 'title', null, 1, 24)));
        self::assertSame([$a, $b], $ids($this->items->rubricItems($this->porcelain, 'year', 1950, 1, 24)));
        self::assertSame(3, $this->items->rubricItems($this->porcelain, 'author', null, 2, 2)['total']);
        self::assertSame([1950 => 2, 1960 => 1], $this->items->decades($this->porcelain));

        $around = $this->items->neighbours($this->porcelain, $b);
        self::assertSame($c, $around['prev']['id'] ?? null);
        self::assertSame($a, $around['next']['id'] ?? null);
        self::assertSame([$a, $c], array_column($this->items->series($this->porcelain, $b, 4), 'id'));

        $search = fn(string $q): array => $ids($this->items->search($q, [$this->porcelain], 'author', 1, 24));
        self::assertSame([$c], $search('рысак'));
        self::assertSame([$b], $search('бискв'), 'Characteristics and word prefixes are searched');
        self::assertSame([$b], $search('ЛФЗ-1042'), 'The exact number');
        self::assertSame([], $search('черновик'), 'Drafts are not found');
        self::assertSame([], $this->items->search('рысак', [$this->sets], 'author', 1, 24)['rows'], 'Only the given rubrics');
    }

    private function rubric(string $name, int $parent = 0): int
    {
        return $this->rubrics->create(['parent' => $parent, 'name' => $name, 'title' => '', 'slug' => '', 'description' => '', 'visible' => true]);
    }

    /**
     * @param list<int>            $rubrics
     * @param array<string, mixed> $extra
     */
    private function item(string $catno, string $name, array $rubrics, array $extra = []): int
    {
        $input = $extra + $this->input($catno, $name, $rubrics);
        /** @var array{catno: string, name: string, summary: string, description: string, yearFrom: string, yearTo: string, published: bool, rubrics: list<int>, main: int, attributes: list<array{name: string, value: string}>, variants: list<array{number: string, finish: string, years: string}>} $input */
        self::assertSame([], $this->items->validate($input));

        return $this->items->save(null, $input, null);
    }

    /**
     * @param list<int> $rubrics
     *
     * @return array{catno: string, name: string, summary: string, description: string, yearFrom: string, yearTo: string, published: bool,
     *     rubrics: list<int>, main: int, attributes: list<array{name: string, value: string}>, variants: list<array{number: string, finish: string, years: string}>}
     */
    private function input(string $catno, string $name, array $rubrics): array
    {
        return [
            'catno' => $catno, 'name' => $name, 'summary' => '', 'description' => '', 'yearFrom' => '', 'yearTo' => '',
            'published' => true, 'rubrics' => $rubrics, 'main' => $rubrics[0] ?? 0, 'attributes' => [], 'variants' => [],
        ];
    }

    /**
     * @phpstan-impure
     *
     * @return list<int>
     */
    private function order(int $rubric): array
    {
        return $this->db->ints('SELECT item_id FROM item_rubrics WHERE rubric_id = :r ORDER BY position, item_id', ['r' => $rubric]);
    }
}
