<?php

/**
 * Items as catalogue cards; the list view (.is-list) also shows the summary.
 * Photos arrive in build 5; until then the mount holds a placeholder.
 *
 * @var \Estables\Core\View $this
 * @var list<array{id: int, catno: string, slug: string, name: string, summary: string, yearFrom: ?int, yearTo: ?int, published: bool,
 *     updatedAt: \DateTimeImmutable, variants: int, main: int, rubrics: list<int>}> $rows
 * @var string              $class  grid classes
 * @var bool                $toggle the grid/list switch drives this list
 */
$class ??= 'row row-cols-2 row-cols-md-3 row-cols-xxl-4 g-3 g-md-4 es-catalog';
$toggle ??= true;
?>
      <div class="<?= $this->e($class) ?>"<?= $toggle ? ' data-es-catalog' : '' ?>>
<?php foreach ($rows as $row):
    $meta = \Estables\Http\CatalogController::cardMeta($row['yearFrom'], $row['yearTo'], $row['variants']);
?>
        <div class="col"><article class="es-specimen">
          <div class="es-mount ratio ratio-4x3"><div class="es-mount-empty"><i class="bi bi-image" aria-hidden="true"></i></div></div>
          <div class="es-label">
            <span class="es-catno"><?= $this->e($row['catno']) ?></span>
            <h3><a href="/item/<?= $this->e($row['slug']) ?>/"><?= $this->e($row['name']) ?></a></h3>
<?php if ($meta !== '' || !$row['published']): ?>
            <p class="es-meta"><?= $this->e($meta) ?><?php if (!$row['published']): ?> <span class="es-status es-status-draft">Черновик</span><?php endif ?></p>
<?php endif ?>
<?php if ($row['summary'] !== ''): ?>
            <p class="es-desc text-body-secondary"><?= $this->e($row['summary']) ?></p>
<?php endif ?>
          </div>
        </article></div>
<?php endforeach ?>
      </div>
