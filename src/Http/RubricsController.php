<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Http;

use Estables\Audit\Changes;
use Estables\Catalog\Rubric;
use Estables\Catalog\RubricException;
use Estables\Catalog\RubricTree;
use Estables\Core\HttpException;
use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Core\Services;
use Estables\Core\View;
use Estables\Media\Image;
use Estables\Media\MediaException;

/**
 * Admin page "Rubricator": the tree on the left, the form of the selected
 * or new rubric on the right. Drag and drop in the tree posts every move to
 * /admin/rubrics/move/. The kernel has already checked the CSRF token.
 *
 * @phpstan-import-type RubricInput from \Estables\Catalog\RubricRepository
 * @phpstan-import-type RubricState from Changes
 */
final class RubricsController
{
    private const PATH = '/admin/rubrics/';

    private const NOTICES = [
        'created' => 'Раздел добавлен.',
        'saved' => 'Раздел сохранён.',
        'deleted' => 'Раздел удалён.',
    ];

    public function __construct(
        private readonly Services $services,
        private readonly View $view,
    ) {}

    /**
     * @param array<string, string> $params
     */
    public function handle(Request $request, array $params): Response
    {
        $rubrics = $this->services->rubrics();
        $errors = [];
        $failure = null;
        $input = null;

        if ($request->isPost()) {
            $id = (int) $request->input('id');
            if ($request->input('action') === 'delete') {
                try {
                    $tree = $rubrics->tree();
                    $parent = $tree->has($id) ? $tree->node($id)['parent'] : 0;
                    $mode = $request->input('items');
                    $stats = $this->services->items()->rubricStats()[$id] ?? ['items' => 0, 'shared' => 0];
                    if ($mode !== 'move' && $mode !== 'delete' && $stats['items'] > $stats['shared']) {
                        throw new RubricException('Выберите, что сделать с предметами раздела: перенести или удалить.');
                    }
                    $target = $mode === 'move' ? (int) $request->input('target') : null;
                    if ($target !== null && ($target === $id || !$tree->has($target))) {
                        throw new RubricException('Выберите другой раздел, куда перенести предметы.');
                    }
                    $name = $tree->has($id) ? $tree->node($id)['name'] : "№{$id}";
                    $released = $this->services->db()->transaction(function () use ($rubrics, $id, $target): array {
                        if ($rubrics->tree()->children($id) !== []) {
                            throw new RubricException('Сначала перенесите или удалите подразделы этого раздела.');
                        }
                        $released = $this->services->items()->release($id, $target);
                        $rubrics->delete($id);

                        return $released;
                    });
                    $rubrics->invalidate();
                    $this->services->audit()->record('rubric.delete', $name, $id, self::releasedText($released, $target, $tree));

                    return Response::redirect(self::PATH . ($parent !== 0 ? "?id={$parent}&" : '?') . 'done=deleted', 303);
                } catch (RubricException $e) {
                    $failure = $e->getMessage();
                    $request = new Request('GET', $request->path, ['id' => (string) $id]);
                }
            } else {
                $input = self::input($request);
                $errors = $rubrics->validate($input, $id > 0 ? $id : null);
                $cover = $this->cover($request, $errors);
                if ($errors === []) {
                    $existing = $id > 0 ? $rubrics->find($id) : null;
                    $before = $existing === null ? null : self::state($existing);
                    if ($id > 0) {
                        $rubrics->update($id, $input);
                    } else {
                        $id = $rubrics->create($input);
                    }
                    if ($cover !== null) {
                        $rubrics->setCover($id, $cover->sha);
                    } elseif ($request->input('remove_cover') === '1') {
                        $rubrics->setCover($id, null);
                    }
                    $this->auditSave($id, $before);

                    return Response::redirect(self::PATH . "?id={$id}&done=" . ($request->input('id') === '0' ? 'created' : 'saved'), 303);
                }
            }
        }

        $tree = $rubrics->tree();
        $selected = (int) ($request->isPost() ? $request->input('id') : self::query($request, 'id'));
        $rubric = $selected > 0 ? $rubrics->find($selected) : null;
        if ($selected > 0 && $rubric === null) {
            throw HttpException::notFound();
        }
        $creating = $rubric === null && ($tree->isEmpty() || self::query($request, 'new') !== '' || $input !== null);
        if ($creating) {
            $parent = $input['parent'] ?? (int) self::query($request, 'parent');
            $input ??= ['parent' => $tree->has($parent) ? $parent : 0, 'name' => '', 'title' => '', 'slug' => '', 'description' => '', 'visible' => true];
        } elseif ($rubric !== null) {
            $input ??= [
                'parent' => $rubric->parentId ?? 0,
                'name' => $rubric->name,
                'title' => $rubric->title,
                'slug' => $rubric->slug,
                'description' => $rubric->description,
                'visible' => $rubric->visible,
            ];
        }

        $done = self::query($request, 'done');

        return Response::html($this->view->page('admin/rubrics', [
            'title' => 'Рубрикатор',
            'header' => 'admin',
            'activeNav' => 'rubrics',
            'noindex' => true,
            'tree' => $tree,
            'rubric' => $rubric,
            'creating' => $creating,
            'form' => $input,
            'errors' => $errors,
            'failure' => $failure,
            'notice' => self::NOTICES[$done] ?? null,
            'open' => $this->openBranches($tree, $rubric, $creating ? $input['parent'] : 0),
            'parents' => $input === null ? [] : $this->parentOptions($tree, $rubric),
            'stats' => $this->services->items()->rubricStats(),
            'targets' => ItemsController::rubricOptions($tree),
            'images' => $this->services->images(),
        ]), $errors === [] && $failure === null ? 200 : 422);
    }

    /**
     * The uploaded cover, cleaned and stored; a problem goes to $errors['cover'].
     *
     * @param array<string, string> $errors
     */
    private function cover(Request $request, array &$errors): ?Image
    {
        $file = $request->file('cover');
        if ($file === null) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['cover'] = $file['error'] === UPLOAD_ERR_INI_SIZE ? 'Файл больше, чем разрешает сервер.' : 'Файл не загрузился. Попробуйте ещё раз.';

            return null;
        }
        try {
            return $this->services->ingest()->ingest($file['tmp'], $file['name']);
        } catch (MediaException $e) {
            $errors['cover'] = $e->getMessage();

            return null;
        }
    }

    /**
     * A drag-and-drop move: answers JSON {ok: true} or {ok: false, error}.
     *
     * @param array<string, string> $params
     */
    public function move(Request $request, array $params): Response
    {
        $id = (int) $request->input('id');
        try {
            $rubrics = $this->services->rubrics();
            $from = $rubrics->tree()->has($id) ? $rubrics->tree()->node($id)['parent'] : null;
            $rubrics->move($id, (int) $request->input('parent'), (int) $request->input('index'));
        } catch (RubricException $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 409);
        }
        $tree = $rubrics->tree();
        if ($tree->has($id)) {
            $parent = $tree->node($id)['parent'];
            $where = $parent > 0 ? 'в «' . $tree->node($parent)['name'] . '»' : 'на верхний уровень';
            $this->services->audit()->record('rubric.move', $tree->node($id)['name'], $id, ($from === $parent ? 'новое место ' : 'перенесён ') . $where . ', позиция ' . ((int) $request->input('index') + 1));
        }

        return Response::json(['ok' => true]);
    }

    /**
     * @return RubricState
     */
    private static function state(Rubric $rubric): array
    {
        return [
            'parent' => $rubric->parentId ?? 0,
            'name' => $rubric->name,
            'title' => $rubric->title,
            'slug' => $rubric->slug,
            'description' => $rubric->description,
            'visible' => $rubric->visible,
            'cover' => $rubric->cover,
        ];
    }

    /**
     * @param RubricState|null $before null for a new rubric
     */
    private function auditSave(int $id, ?array $before): void
    {
        $rubric = $this->services->rubrics()->find($id);
        if ($rubric === null) {
            return;
        }
        $tree = $this->services->rubrics()->tree();
        if ($before === null) {
            $parent = $rubric->parentId !== null && $tree->has($rubric->parentId) ? 'в «' . $tree->node($rubric->parentId)['name'] . '»' : 'на верхнем уровне';
            $this->services->audit()->record('rubric.create', $rubric->name, $id, $parent . ($rubric->visible ? '' : ', скрыт'));

            return;
        }
        $changes = Changes::rubric($before, self::state($rubric), $tree);
        if ($changes !== '') {
            $this->services->audit()->record('rubric.update', $rubric->name, $id, $changes);
        }
    }

    /**
     * @param array{moved: int, deleted: int, unlinked: int} $released
     */
    private static function releasedText(array $released, ?int $target, RubricTree $tree): string
    {
        $parts = [];
        if ($released['moved'] > 0) {
            $parts[] = 'перенесено предметов: ' . $released['moved'] . ($target !== null && $tree->has($target) ? ' в «' . $tree->node($target)['name'] . '»' : '');
        }
        if ($released['deleted'] > 0) {
            $parts[] = 'удалено предметов: ' . $released['deleted'];
        }
        if ($released['unlinked'] > 0) {
            $parts[] = 'остались в других разделах: ' . $released['unlinked'];
        }

        return implode('; ', $parts);
    }

    /**
     * @return RubricInput
     */
    private static function input(Request $request): array
    {
        return [
            'parent' => (int) $request->input('parent'),
            'name' => $request->input('name'),
            'title' => $request->input('title'),
            'slug' => strtolower($request->input('slug')),
            'description' => str_replace("\r\n", "\n", $request->input('description')),
            'visible' => $request->input('visible') === '1',
        ];
    }

    private static function query(Request $request, string $name): string
    {
        $value = $request->query[$name] ?? '';

        return \is_string($value) ? $value : '';
    }

    /**
     * Branches shown expanded: the path to the selected rubric (or to the
     * parent of a new one).
     *
     * @return array<int, true>
     */
    private function openBranches(RubricTree $tree, ?Rubric $rubric, int $newParent): array
    {
        $ids = [];
        if ($rubric !== null && $tree->has($rubric->id)) {
            $ids = $tree->ancestors($rubric->id);
        } elseif ($newParent !== 0 && $tree->has($newParent)) {
            $ids = [...$tree->ancestors($newParent), $newParent];
        }

        return array_fill_keys($ids, true);
    }

    /**
     * Choices for "Parent rubric": the top level and every rubric except the
     * edited one and its subtree, with the address prefix each one gives.
     *
     * @return list<array{id: int, label: string, prefix: string}>
     */
    private function parentOptions(RubricTree $tree, ?Rubric $rubric): array
    {
        $options = [['id' => 0, 'label' => 'Верхний уровень', 'prefix' => '/catalog/']];
        foreach ($tree->flatten($rubric?->id) as ['id' => $id, 'depth' => $depth]) {
            $options[] = [
                'id' => $id,
                'label' => str_repeat("\u{00A0}\u{00A0}", $depth) . $tree->node($id)['name'],
                'prefix' => ($depth === 0 ? '/catalog/' : '…/') . $tree->node($id)['slug'] . '/',
            ];
        }

        return $options;
    }
}
