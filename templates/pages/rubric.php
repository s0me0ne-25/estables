<?php

/**
 * Rubric page: path, heading with description and figures, subrubrics,
 * then the items linked to the rubric directly.
 *
 * @var \Estables\Core\View          $this
 * @var \Estables\Catalog\RubricTree $tree
 * @var \Estables\Catalog\Rubric     $rubric
 * @var list<int>                    $subrubrics subrubrics the visitor may see
 * @var bool                         $admin
 * @var list<array{id: int, catno: string, slug: string, name: string, summary: string, yearFrom: ?int, yearTo: ?int, published: bool,
 *     updatedAt: \DateTimeImmutable, variants: int, main: int, rubrics: list<int>}> $rows
 * @var \Estables\Core\Pager         $pager
 * @var string                       $sort
 * @var array<string, string>        $sorts
 * @var array<int, int>              $decades   items by decade
 * @var int|null                     $decade    chosen decade
 * @var int                          $directTotal items linked directly, without the decade filter
 * @var \Closure(array<string, int|string|null>): string $links
 */
$paragraphs = array_values(array_filter(array_map('trim', preg_split('~\n\s*\n~', trim($rubric->description)) ?: [])));
$node = $tree->node($rubric->id);
$years = \Estables\Catalog\Item::yearRange($node['yearMin'], $node['yearMax']);
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
<?php if ($node['count'] > 0): ?>
        <div class="col-xl-4">
          <dl class="es-facts justify-content-xl-end">
            <div><dt>Моделей</dt><dd><?= $this->e(\Estables\Core\Text::number($node['count'])) ?></dd></div>
            <div><dt>Вариантов</dt><dd><?= $this->e(\Estables\Core\Text::number($node['variants'])) ?></dd></div>
<?php if ($years !== ''): ?>
            <div><dt>Годы выпуска</dt><dd><?= $this->e($years) ?></dd></div>
<?php endif ?>
          </dl>
        </div>
<?php endif ?>
      </div>

<?php if ($subrubrics !== []): ?>
<?php if ($directTotal === 0): ?>
<?= $this->partial('partials/rubricator-button') ?>
<?php endif ?>
      <h2 class="h4 mb-3">Подразделы</h2>
<?= $this->partial('partials/rubric-tiles', ['tree' => $tree, 'ids' => $subrubrics, 'admin' => $admin]) ?>
<?php endif ?>

<?php if ($directTotal > 0): ?>
<?php if (\count($decades) > 1): ?>
      <div class="es-chips mb-4" role="group" aria-label="Десятилетие выпуска">
        <a class="es-chip<?= $decade === null ? ' active' : '' ?>" href="<?= $this->e($links(['decade' => null])) ?>"<?= $decade === null ? ' aria-current="true"' : '' ?>>Все <small><?= $directTotal ?></small></a>
<?php foreach ($decades as $start => $count): ?>
        <a class="es-chip<?= $decade === $start ? ' active' : '' ?>" href="<?= $this->e($links(['decade' => $start])) ?>"<?= $decade === $start ? ' aria-current="true"' : '' ?>><?= $start ?>-е <small><?= $count ?></small></a>
<?php endforeach ?>
      </div>
<?php endif ?>

<?= $this->partial('partials/catalog-toolbar', ['pager' => $pager, 'sort' => $sort, 'sorts' => $sorts]) ?>
<?= $this->partial('partials/item-cards', ['rows' => $rows]) ?>
<?= $this->partial('partials/pagination', ['pager' => $pager, 'pageUrl' => fn(int $p): string => $links(['page' => $p > 1 ? $p : null]), 'label' => 'Страницы каталога', 'class' => 'justify-content-center', 'navClass' => 'mt-5']) ?>
<?php elseif ($subrubrics === []): ?>
<?= $this->partial('partials/rubricator-button') ?>
      <p class="es-meta">В разделе пока нет предметов.</p>
<?php endif ?>
    </main>
</div>
