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
 * @var bool                $gallery     the page needs the full-screen photo viewer (GLightbox)
 * @var string              $siteUrl     absolute address of the site, without a trailing slash
 * @var string              $canonical   address of the page to index (path), "" for none
 * @var string              $ogImage     picture for shared links (path), "" for none
 * @var string              $ogType      Open Graph type: "website" or "article"
 */
$canonical ??= '';
$ogImage ??= '';
$ogType ??= 'website';
$siteUrl ??= '';
$gallery ??= false;
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
<?php elseif ($canonical !== ''): ?>
  <link rel="canonical" href="<?= $this->e($siteUrl . $canonical) ?>">
  <meta property="og:site_name" content="eStables">
  <meta property="og:locale" content="ru_RU">
  <meta property="og:type" content="<?= $this->e($ogType) ?>">
  <meta property="og:title" content="<?= $this->e($title === '' ? 'eStables' : $title) ?>">
<?php if ($description !== ''): ?>
  <meta property="og:description" content="<?= $this->e($description) ?>">
<?php endif ?>
  <meta property="og:url" content="<?= $this->e($siteUrl . $canonical) ?>">
<?php if ($ogImage !== ''): ?>
  <meta property="og:image" content="<?= $this->e($siteUrl . $ogImage) ?>">
  <meta name="twitter:card" content="summary_large_image">
<?php else: ?>
  <meta name="twitter:card" content="summary">
<?php endif ?>
<?php endif ?>

  <link rel="stylesheet" href="<?= $this->e($this->asset('vendor/fonts/fonts.css')) ?>">
  <link rel="stylesheet" href="<?= $this->e($this->asset('vendor/bootstrap/css/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= $this->e($this->asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<?php if ($gallery): ?>
  <link rel="stylesheet" href="<?= $this->e($this->asset('vendor/glightbox/css/glightbox.min.css')) ?>">
<?php endif ?>
  <link rel="stylesheet" href="<?= $this->e($this->asset('css/estables.css')) ?>">

  <!-- Loaded in <head> on purpose: sets the colour theme before first paint. -->
  <script src="<?= $this->e($this->asset('js/estables.js')) ?>"></script>
</head>
<body>

<a class="visually-hidden-focusable position-absolute top-0 start-0 p-2 bg-body" href="#main">Перейти к содержанию</a>

<?= $this->partial('partials/header-' . $header, ['activeNav' => $activeNav, 'searchQuery' => $searchQuery ?? '']) ?>

<?= $content ?>

<?= $this->partial('partials/footer') ?>

<script src="<?= $this->e($this->asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<?php if ($gallery): ?>
<script src="<?= $this->e($this->asset('vendor/glightbox/js/glightbox.min.js')) ?>"></script>
<?php endif ?>
</body>
</html>
