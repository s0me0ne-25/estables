<?php

/**
 * One level of the admin rubric tree, recursively.
 *
 * @var \Estables\Core\View         $this
 * @var \Estables\Catalog\RubricTree $tree
 * @var int                         $parent    rubric whose children are listed (0: top level)
 * @var int                         $selected  rubric being edited, 0 if none
 * @var array<int, true>            $open      expanded branches
 * @var array<int, array{items: int, shared: int}> $stats items linked directly, and how many of them have other rubrics
 */
foreach ($tree->children($parent) as $id):
    $node = $tree->node($id);
    $children = $tree->children($id);
    $name = $this->e($node['name']);
    $class = $id === $selected ? 'is-current' : (isset($open[$id]) ? 'is-lineage' : '');
?>
<li data-es-rubric="<?= $id ?>"<?= $class !== '' ? ' class="' . $class . '"' : '' ?>>
  <div class="es-node">
    <button class="es-drag" data-es-sortable-handle type="button" title="Перетащить" aria-label="Переместить «<?= $name ?>»"><i class="bi bi-grip-vertical" aria-hidden="true"></i></button>
<?php if ($children !== []): ?>
    <button class="es-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#a-<?= $id ?>" aria-expanded="<?= isset($open[$id]) ? 'true' : 'false' ?>" aria-controls="a-<?= $id ?>" aria-label="Подразделы"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
<?php else: ?>
    <span class="es-toggle-spacer"></span>
<?php endif ?>
    <a href="/admin/rubrics/?id=<?= $id ?>"<?= $id === $selected ? ' aria-current="true"' : '' ?>><span><?= $name ?><?php if (!$node['visible']): ?><i class="bi bi-eye-slash es-hidden-mark" aria-hidden="true" title="Скрыт на сайте"></i><span class="visually-hidden">, скрыт на сайте</span><?php endif ?></span> <span class="es-count"><?= $node['count'] ?></span></a>
    <div class="es-node-actions">
      <button class="btn btn-sm es-btn-quiet" type="button" title="Добавить подраздел" data-es-href="/admin/rubrics/?new=1&amp;parent=<?= $id ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i><span class="visually-hidden">Добавить подраздел в «<?= $name ?>»</span></button>
      <button class="btn btn-sm es-btn-quiet es-btn-quiet-danger" type="button" title="Удалить" data-bs-toggle="modal" data-bs-target="#deleteRubricModal" data-es-rubric-delete="<?= $id ?>" data-es-rubric-name="<?= $name ?>" data-es-rubric-children="<?= \count($children) ?>" data-es-rubric-items="<?= $stats[$id]['items'] ?? 0 ?>" data-es-rubric-shared="<?= $stats[$id]['shared'] ?? 0 ?>"><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">Удалить «<?= $name ?>»</span></button>
    </div>
  </div>
<?php if ($children !== []): ?>
  <ul class="collapse<?= isset($open[$id]) ? ' show' : '' ?>" id="a-<?= $id ?>">
<?= $this->partial('admin/rubric-branch', ['tree' => $tree, 'parent' => $id, 'selected' => $selected, 'open' => $open, 'stats' => $stats]) ?>
  </ul>
<?php endif ?>
</li>
<?php endforeach ?>
