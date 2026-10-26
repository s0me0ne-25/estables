<?php

/**
 * Captcha stub: an "I am human" checkbox in the frame of a real widget.
 *
 * @var \Estables\Core\View $this
 * @var string              $field
 */
?>
<div class="es-captcha">
  <div class="form-check">
    <input class="form-check-input" type="checkbox" id="captcha" name="<?= $this->e($field) ?>" value="1">
    <label class="form-check-label" for="captcha">Подтвердите, что вы человек</label>
  </div>
  <div class="es-captcha-mark" aria-hidden="true">
    <i class="bi bi-shield-check"></i>
    <span>проверка</span>
  </div>
</div>
