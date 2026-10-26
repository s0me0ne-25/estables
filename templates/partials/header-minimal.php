<?php

/**
 * Reduced header for the installer and service pages: logo and theme switcher.
 *
 * @var \Estables\Core\View $this
 */
?>
<header class="es-header">
  <nav class="navbar" aria-label="Основная навигация">
    <div class="container-fluid px-3 px-lg-4">
      <?= $this->partial('partials/brand', ['brandHref' => '/']) ?>
      <?= $this->partial('partials/theme-menu') ?>
    </div>
  </nav>
</header>
