<?php

/**
 * Header of the public site.
 *
 * @var \Estables\Core\View $this
 * @var string              $activeNav   key of the current menu item
 * @var string              $searchQuery text of the search field
 */
$activeNav ??= '';
$searchQuery ??= '';
$items = [
    'catalog' => ['Каталог', $this->url('home')],
    'about' => ['О справочнике', $this->url('about')],
];
?>
<header class="es-header">
  <nav class="navbar navbar-expand-md" aria-label="Основная навигация">
    <div class="container-fluid px-3 px-lg-4">
      <?= $this->partial('partials/brand', ['brandHref' => $this->url('home')]) ?>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Открыть меню">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav me-auto mb-2 mb-md-0">
          <?php foreach ($items as $key => [$label, $href]): ?>
            <?php if ($key === $activeNav): ?>
              <li class="nav-item"><a class="nav-link active" aria-current="page" href="<?= $this->e($href) ?>"><?= $this->e($label) ?></a></li>
            <?php else: ?>
              <li class="nav-item"><a class="nav-link" href="<?= $this->e($href) ?>"><?= $this->e($label) ?></a></li>
            <?php endif ?>
          <?php endforeach ?>
        </ul>

        <form class="d-flex gap-2 align-items-center" role="search" method="get" action="/search/">
          <label class="visually-hidden" for="q">Поиск по каталогу</label>
          <input class="form-control" type="search" id="q" name="q" value="<?= $this->e($searchQuery) ?>" placeholder="Поиск по каталогу">
          <?= $this->partial('partials/theme-menu') ?>
        </form>
      </div>
    </div>
  </nav>
</header>
