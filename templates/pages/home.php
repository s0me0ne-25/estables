<?php

/**
 * Catalogue root: the rubricator and the top-level rubrics.
 *
 * @var \Estables\Core\View          $this
 * @var \Estables\Catalog\RubricTree $tree
 * @var list<int>                    $top   top-level rubrics the visitor may see
 * @var bool                         $admin
 */
?>
<div class="es-layout">

<?= $this->partial('partials/rubricator', ['tree' => $tree, 'current' => 0, 'admin' => $admin]) ?>

    <!-- ======================= Main content ======================= -->
    <main id="main" class="es-main px-3 px-lg-4 px-xxl-5 py-4">

      <nav aria-label="Путь по разделам">
        <ol class="breadcrumb">
          <li class="breadcrumb-item active" aria-current="page">Каталог</li>
        </ol>
      </nav>

      <h1 class="mb-3">Каталог</h1>
<?php if ($top === []): ?>
      <p class="es-lead">Справочник пока пуст. Разделы и предметы появятся здесь, когда администратор их добавит.</p>
<?php else: ?>
<?= $this->partial('partials/rubricator-button') ?>
<?= $this->partial('partials/rubric-tiles', ['tree' => $tree, 'ids' => $top, 'admin' => $admin]) ?>
<?php endif ?>
    </main>
</div>
