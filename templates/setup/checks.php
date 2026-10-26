<?php

/**
 * Environment check results.
 *
 * @var \Estables\Core\View           $this
 * @var list<\Estables\Install\Check> $checks
 */
$icons = ['ok' => 'bi-check-circle', 'warning' => 'bi-exclamation-triangle', 'error' => 'bi-x-circle'];
$labels = ['ok' => 'в порядке', 'warning' => 'предупреждение', 'error' => 'ошибка'];
?>
<ul class="es-checks">
<?php foreach ($checks as $check): ?>
  <li class="is-<?= $this->e($check->status) ?>">
    <i class="bi <?= $this->e($icons[$check->status]) ?>" aria-hidden="true"></i>
    <span><span class="visually-hidden"><?= $this->e($labels[$check->status]) ?>: </span><?= $this->e($check->title) ?>
<?php if (!$check->ok()): ?>
      <span class="es-meta d-block"><?= $this->e($check->hint) ?></span>
<?php endif ?>
    </span>
  </li>
<?php endforeach ?>
</ul>
