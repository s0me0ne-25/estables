<?php

/**
 * The site cannot start: configuration errors or a failed environment check.
 *
 * @var \Estables\Core\View                 $this
 * @var string                              $heading
 * @var string                              $text
 * @var list<string>                        $problems plain messages
 * @var list<\Estables\Install\Check>       $checks   environment check lines
 */
$problems ??= [];
$checks ??= [];
?>
<main id="main" class="es-auth-page">
  <div class="es-auth-card es-auth-card-wide">
    <h1 class="h2 mb-2"><?= $this->e($heading) ?></h1>
    <p class="text-body-secondary"><?= $this->e($text) ?></p>

<?php if ($problems !== []): ?>
    <ul class="es-checks">
<?php foreach ($problems as $problem): ?>
      <li class="is-error"><i class="bi bi-x-circle" aria-hidden="true"></i><span><?= $this->e($problem) ?></span></li>
<?php endforeach ?>
    </ul>
<?php endif ?>

<?php if ($checks !== []): ?>
    <?= $this->partial('setup/checks', ['checks' => $checks]) ?>
<?php endif ?>

    <p class="es-meta mb-0">Исправьте это на сервере и обновите страницу.</p>
  </div>
</main>
