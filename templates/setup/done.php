<?php

/**
 * Installer finished.
 *
 * @var \Estables\Core\View $this
 * @var string              $login
 */
?>
<main id="main" class="es-auth-page">
  <div class="es-auth-card">
    <h1 class="h2 mb-2">Установка завершена</h1>
    <p class="text-body-secondary">Администратор «<?= $this->e($login) ?>» создан, токен установки удалён.</p>
    <p class="text-body-secondary mb-4">Вход в панель управления появится в следующей сборке.</p>
    <a class="btn btn-primary w-100" href="/">Перейти на сайт</a>
  </div>
</main>
