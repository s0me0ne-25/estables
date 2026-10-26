<?php

/**
 * Sidebar with the rubricator; an offcanvas panel below the lg breakpoint
 * (opened by partials/rubricator-button).
 *
 * @var \Estables\Core\View          $this
 * @var \Estables\Catalog\RubricTree $tree
 * @var int                          $current rubric of the page, 0 on the catalogue root
 * @var bool                         $admin
 */
$open = $current !== 0 ? array_fill_keys($tree->ancestors($current), true) : [];
$hasRubrics = false;
foreach ($tree->children(0) as $id) {
    $hasRubrics = $hasRubrics || $admin || $tree->node($id)['visible'];
}
?>
    <!-- ======================= Rubricator ======================= -->
    <aside class="es-aside">
      <div class="offcanvas-lg offcanvas-start es-sidebar" tabindex="-1" id="rubricator" aria-labelledby="rubricatorTitle">
        <div class="offcanvas-header">
          <h2 class="offcanvas-title es-sidebar-title mb-0" id="rubricatorTitle">Рубрикатор</h2>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#rubricator" aria-label="Закрыть"></button>
        </div>
        <div class="offcanvas-body d-block p-3 p-lg-4">
          <h2 class="es-sidebar-title d-none d-lg-block">Рубрикатор</h2>
<?php if ($hasRubrics): ?>

          <nav aria-label="Рубрикатор">
            <ul class="es-tree">
<?= $this->partial('partials/rubric-nav', ['tree' => $tree, 'parent' => 0, 'current' => $current, 'open' => $open, 'admin' => $admin]) ?>
            </ul>
          </nav>
<?php else: ?>
          <p class="es-meta">Разделов пока нет.</p>
<?php endif ?>
        </div>
      </div>
    </aside>
