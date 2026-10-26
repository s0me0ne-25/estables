<?php

/**
 * One level of the public rubricator, recursively.
 *
 * @var \Estables\Core\View          $this
 * @var \Estables\Catalog\RubricTree $tree
 * @var int                          $parent  rubric whose children are listed (0: top level)
 * @var int                          $current rubric of the page, 0 on the catalogue root
 * @var array<int, true>             $open    expanded branches: the path to the current rubric
 * @var bool                         $admin   an administrator also sees hidden rubrics
 */
foreach ($tree->children($parent) as $id):
    $node = $tree->node($id);
    if (!$node['visible'] && !$admin) {
        continue;
    }
    $children = array_values(array_filter($tree->children($id), static fn(int $c): bool => $admin || $tree->node($c)['visible']));
    $class = $id === $current ? 'is-current' : (isset($open[$id]) ? 'is-lineage' : '');
    $expanded = isset($open[$id]) || $id === $current;
?>
<li<?= $class !== '' ? ' class="' . $class . '"' : '' ?>>
  <div class="es-node">
<?php if ($children !== []): ?>
    <button class="es-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#t-<?= $id ?>" aria-expanded="<?= $expanded ? 'true' : 'false' ?>" aria-controls="t-<?= $id ?>" aria-label="Подразделы"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
<?php else: ?>
    <span class="es-toggle-spacer"></span>
<?php endif ?>
    <a href="<?= $this->e($tree->url($id)) ?>"<?= $id === $current ? ' aria-current="page"' : '' ?>><span><?= $this->e($node['name']) ?><?php if (!$node['visible']): ?><i class="bi bi-eye-slash es-hidden-mark" aria-hidden="true" title="Скрыт на сайте"></i><span class="visually-hidden">, скрыт на сайте</span><?php endif ?></span> <span class="es-count"><?= $node['count'] ?></span></a>
  </div>
<?php if ($children !== []): ?>
  <ul class="collapse<?= $expanded ? ' show' : '' ?>" id="t-<?= $id ?>">
<?= $this->partial('partials/rubric-nav', ['tree' => $tree, 'parent' => $id, 'current' => $current, 'open' => $open, 'admin' => $admin]) ?>
  </ul>
<?php endif ?>
</li>
<?php endforeach ?>
