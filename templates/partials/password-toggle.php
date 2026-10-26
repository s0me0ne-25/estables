<?php

/**
 * Eye button that shows a password in clear text; goes into the field's
 * .input-group right after the input. See initPasswordToggles in estables.js.
 *
 * @var \Estables\Core\View $this
 * @var string              $for     id of the password input
 * @var string              $confirm id of the confirmation input to hide while
 *                                   the password is shown; empty if there is none
 */
$confirm ??= '';
?>
<button class="btn btn-outline-secondary" type="button" data-es-password-toggle<?php if ($confirm !== ''): ?> data-es-password-confirm="<?= $this->e($confirm) ?>"<?php endif ?> aria-controls="<?= $this->e($for) ?>" aria-pressed="false" title="Показать пароль">
  <i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">Показать пароль</span>
</button>
