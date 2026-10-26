<?php

/**
 * Rubric page: path, heading and description, subrubrics. Items arrive in build 4.
 *
 * @var \Estables\Core\View          $this
 * @var \Estables\Catalog\RubricTree $tree
 * @var \Estables\Catalog\Rubric     $rubric
 * @var list<int>                    $subrubrics subrubrics the visitor may see
 * @var bool                         $admin
 */
$paragraphs = array_values(array_filter(array_map('trim', preg_split('~\n\s*\n~', trim($rubric->description)) ?: [])));
?>
<div class="es-layout">

<?= $this->partial('partials/rubricator', ['tree' => $tree, 'current' => $rubric->id, 'admin' => $admin]) ?>

    <!-- ======================= Main content ======================= -->
    <main id="main" class="es-main px-3 px-lg-4 px-xxl-5 py-4">

      <nav aria-label="Путь по разделам">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="/">Каталог</a></li>
<?php foreach ($tree->ancestors($rubric->id) as $id): ?>
          <li class="breadcrumb-item"><a href="<?= $this->e($tree->url($id)) ?>"><?= $this->e($tree->node($id)['name']) ?></a></li>
<?php endforeach ?>
          <li class="breadcrumb-item active" aria-current="page"><?= $this->e($rubric->name) ?></li>
        </ol>
      </nav>

<?php if (!$tree->isShown($rubric->id)): ?>
      <div class="alert alert-warning" role="status"><i class="bi bi-eye-slash me-1" aria-hidden="true"></i>Раздел скрыт на сайте: посетители его не видят.</div>
<?php endif ?>

      <div class="row g-4 align-items-end mb-4">
        <div class="col-xl-8">
          <h1 class="<?= $paragraphs === [] ? 'mb-0' : 'mb-3' ?>"><?= $this->e($rubric->heading()) ?></h1>
<?php foreach ($paragraphs as $i => $paragraph): ?>
          <p class="es-lead<?= $i === \count($paragraphs) - 1 ? ' mb-0' : '' ?>"><?= nl2br($this->e($paragraph), false) ?></p>
<?php endforeach ?>
        </div>
      </div>

<?= $this->partial('partials/rubricator-button') ?>
<?php if ($subrubrics !== []): ?>
      <h2 class="h4 mb-3">Подразделы</h2>
<?= $this->partial('partials/rubric-tiles', ['tree' => $tree, 'ids' => $subrubrics, 'admin' => $admin]) ?>
<?php endif ?>
      <p class="es-meta">В разделе пока нет предметов.</p>
    </main>
</div>
