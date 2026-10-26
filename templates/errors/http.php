<?php

/**
 * Error and service pages (404, 500, 503...).
 *
 * @var \Estables\Core\View $this
 * @var string              $heading
 * @var string              $text
 * @var bool                $homeLink show a link to the catalogue
 * @var string|null         $details  technical details, only in debug mode
 */
$homeLink ??= true;
$details ??= null;
?>
<main id="main" class="container-xxl px-3 px-lg-4 py-5">
  <h1 class="mb-3"><?= $this->e($heading) ?></h1>
  <p class="es-lead"><?= $this->e($text) ?></p>
<?php if ($homeLink): ?>
  <p class="mt-4 mb-0"><a class="btn btn-primary" href="/">Перейти в каталог</a></p>
<?php endif ?>
<?php if ($details !== null): ?>
  <pre class="es-debug mt-4"><?= $this->e($details) ?></pre>
<?php endif ?>
</main>
