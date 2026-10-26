<?php

/**
 * Page skeleton shared by every page.
 *
 * @var \Estables\Core\View $this
 * @var string              $content     rendered page body
 * @var string              $title       page title without the site name
 * @var string              $description optional meta description
 * @var bool                $noindex     hide the page from search engines
 * @var string              $header      "site" or "minimal"
 * @var string              $activeNav   key of the current main menu item
 */
$description ??= '';
$activeNav ??= '';
$noindex ??= false;
$header ??= 'site';
?>
<!doctype html>
<html lang="ru" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $this->e($title === '' ? 'eStables' : $title . ' — eStables') ?></title>
<?php if ($description !== ''): ?>
  <meta name="description" content="<?= $this->e($description) ?>">
<?php endif ?>
<?php if ($noindex): ?>
  <meta name="robots" content="noindex, nofollow">
<?php endif ?>

  <link rel="stylesheet" href="<?= $this->e($this->asset('vendor/fonts/fonts.css')) ?>">
  <link rel="stylesheet" href="<?= $this->e($this->asset('vendor/bootstrap/css/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= $this->e($this->asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= $this->e($this->asset('css/estables.css')) ?>">

  <!-- Loaded in <head> on purpose: sets the colour theme before first paint. -->
  <script src="<?= $this->e($this->asset('js/estables.js')) ?>"></script>
</head>
<body>

<a class="visually-hidden-focusable position-absolute top-0 start-0 p-2 bg-body" href="#main">Перейти к содержанию</a>

<?= $this->partial('partials/header-' . $header, ['activeNav' => $activeNav]) ?>

<?= $content ?>

<?= $this->partial('partials/footer') ?>

<script src="<?= $this->e($this->asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
</body>
</html>
