<?php

/**
 * Header of the admin area.
 *
 * @var \Estables\Core\View $this
 * @var string              $activeNav key of the current menu item
 */
$activeNav ??= '';
$items = [
    'items' => ['Предметы', $this->url('admin'), 'bi-collection'],
    'rubrics' => ['Рубрикатор', $this->url('admin.rubrics'), 'bi-diagram-3'],
    'users' => ['Пользователи', $this->url('admin.users'), 'bi-people'],
];
?>
<header class="es-header">
  <nav class="navbar navbar-expand-md" aria-label="Навигация администратора">
    <div class="container-fluid px-3 px-lg-4">
      <?= $this->partial('partials/brand', ['brandHref' => $this->url('admin'), 'brandRole' => 'администрирование']) ?>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Открыть меню">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav me-auto mb-2 mb-md-0">
          <?php foreach ($items as $key => [$label, $href, $icon]): ?>
            <?php if ($key === $activeNav): ?>
              <li class="nav-item"><a class="nav-link active" aria-current="page" href="<?= $this->e($href) ?>"><i class="bi <?= $icon ?> me-1" aria-hidden="true"></i><?= $this->e($label) ?></a></li>
            <?php else: ?>
              <li class="nav-item"><a class="nav-link" href="<?= $this->e($href) ?>"><i class="bi <?= $icon ?> me-1" aria-hidden="true"></i><?= $this->e($label) ?></a></li>
            <?php endif ?>
          <?php endforeach ?>
        </ul>
        <div class="d-flex gap-2 align-items-center">
          <a class="btn btn-icon" href="<?= $this->e($this->url('home')) ?>"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Открыть сайт</a>
          <?= $this->partial('partials/theme-menu') ?>
        </div>
      </div>
    </div>
  </nav>
</header>
