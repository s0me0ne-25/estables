<?php

/**
 * Search results among the items the visitor can see.
 *
 * @var \Estables\Core\View          $this
 * @var string                       $searchQuery
 * @var \Estables\Catalog\RubricTree $tree
 * @var bool                         $admin
 * @var list<array{id: int, catno: string, slug: string, name: string, summary: string, yearFrom: ?int, yearTo: ?int, published: bool,
 *     updatedAt: \DateTimeImmutable, variants: int, main: int, rubrics: list<int>}> $rows
 * @var \Estables\Core\Pager         $pager
 * @var string                       $sort
 * @var array<string, string>        $sorts
 * @var \Closure(array<string, int|string|null>): string $links
 */
?>
<div class="es-layout">

<?= $this->partial('partials/rubricator', ['tree' => $tree, 'current' => 0, 'admin' => $admin]) ?>

    <!-- ======================= Main content ======================= -->
    <main id="main" class="es-main px-3 px-lg-4 px-xxl-5 py-4">

      <nav aria-label="Путь по разделам">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="/">Каталог</a></li>
          <li class="breadcrumb-item active" aria-current="page">Поиск</li>
        </ol>
      </nav>

      <h1 class="mb-3">Поиск</h1>
<?php if ($searchQuery === ''): ?>
      <p class="es-lead">Введите название, номер или слово из описания в поле поиска вверху страницы.</p>
<?php elseif ($pager->total === 0): ?>
      <p class="es-lead">По запросу «<?= $this->e($searchQuery) ?>» ничего не нашлось. Проверьте написание или попробуйте другое слово.</p>
<?= $this->partial('partials/rubricator-button') ?>
<?php else: ?>
      <p class="es-lead mb-4">По запросу «<?= $this->e($searchQuery) ?>» найдено: <?= $this->e(\Estables\Core\Text::plural($pager->total, 'предмет', 'предмета', 'предметов')) ?>.</p>
<?= $this->partial('partials/catalog-toolbar', ['pager' => $pager, 'sort' => $sort, 'sorts' => $sorts]) ?>
<?= $this->partial('partials/item-cards', ['rows' => $rows]) ?>
<?= $this->partial('partials/pagination', ['pager' => $pager, 'pageUrl' => fn(int $p): string => $links(['page' => $p > 1 ? $p : null]), 'label' => 'Страницы результатов', 'class' => 'justify-content-center', 'navClass' => 'mt-5']) ?>
<?php endif ?>
    </main>
</div>
