<?php

/**
 * First run: config/config.php does not exist yet.
 *
 * @var \Estables\Core\View $this
 * @var string              $root project directory on the server
 */
?>
<main id="main" class="es-auth-page">
  <div class="es-auth-card es-auth-card-wide">
    <h1 class="h2 mb-2">Установка eStables</h1>
    <p class="text-body-secondary">Сайт ещё не настроен: нет файла конфигурации.</p>

    <ol class="es-steps">
      <li>Скопируйте шаблон на сервере:
        <pre class="es-code">cp <?= $this->e($root) ?>/config/config.template.php \
   <?= $this->e($root) ?>/config/config.php</pre>
      </li>
      <li>Впишите в <code>config/config.php</code> подключение к пустой базе MariaDB и секрет приложения.
        Пароль к базе вводится только в этот файл, через браузер он не передаётся.</li>
      <li>Обновите эту страницу: схема базы создастся автоматически.</li>
    </ol>
    <p class="es-meta mb-0">Подробнее — в <code>docs/INSTALL.md</code>.</p>
  </div>
</main>
