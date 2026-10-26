<?php

/**
 * Installer: create the first administrator. The form is guarded by the
 * one-time token from var/install.token, readable only on the server.
 *
 * @var \Estables\Core\View           $this
 * @var string                        $tokenFile path of the install token on the server
 * @var string                        $root      project directory on the server
 * @var string                        $formToken CSRF token
 * @var array<string, string>         $errors    messages by field
 * @var array<string, string>         $old       previous input, without passwords
 * @var list<\Estables\Install\Check> $warnings  non-blocking environment warnings
 */
$field = function (string $name) use ($errors): string {
    return isset($errors[$name]) ? ' is-invalid' : '';
};
$describedBy = function (string $name, string $help = '') use ($errors): string {
    $ids = array_filter([$help, isset($errors[$name]) ? $name . '-error' : '']);

    return $ids === [] ? '' : ' aria-describedby="' . implode(' ', $ids) . '"';
};
$error = function (string $name) use ($errors): string {
    return isset($errors[$name])
        ? '<div class="invalid-feedback" id="' . $name . '-error">' . $this->e($errors[$name]) . '</div>'
        : '';
};
?>
<main id="main" class="es-auth-page">
  <div class="es-auth-card">
    <h1 class="h2 mb-2">Первый администратор</h1>
    <p class="text-body-secondary mb-4">База данных готова. Осталось создать учётную запись администратора: свободной регистрации на сайте нет.</p>

<?php if (isset($errors['form'])): ?>
    <div class="alert alert-danger" role="alert"><?= $this->e($errors['form']) ?></div>
<?php endif ?>

    <form method="post" action="/setup/" novalidate>
      <input type="hidden" name="_token" value="<?= $this->e($formToken) ?>">

      <div class="mb-3">
        <label class="form-label" for="install_token">Токен установки</label>
        <input class="form-control<?= $field('install_token') ?>" type="text" id="install_token" name="install_token"
               value="<?= $this->e($old['install_token'] ?? '') ?>" autocomplete="off" spellcheck="false" required<?= $describedBy('install_token', 'install_token-help') ?>>
        <?= $error('install_token') ?>
        <div class="form-text" id="install_token-help">Он лежит на сервере в файле <code><?= $this->e($tokenFile) ?></code>. Так форму не сможет заполнить посторонний.</div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="login">Логин</label>
        <input class="form-control<?= $field('login') ?>" type="text" id="login" name="login"
               value="<?= $this->e($old['login'] ?? '') ?>" autocomplete="username" autocapitalize="off" spellcheck="false" required<?= $describedBy('login') ?>>
        <?= $error('login') ?>
      </div>

      <div class="mb-3">
        <label class="form-label" for="display_name">Имя</label>
        <input class="form-control<?= $field('display_name') ?>" type="text" id="display_name" name="display_name"
               value="<?= $this->e($old['display_name'] ?? '') ?>" autocomplete="name"<?= $describedBy('display_name', 'display_name-help') ?>>
        <?= $error('display_name') ?>
        <div class="form-text" id="display_name-help">Необязательно. Показывается в списке пользователей.</div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="password">Пароль</label>
        <div class="input-group has-validation">
          <input class="form-control<?= $field('password') ?>" type="password" id="password" name="password"
                 autocomplete="new-password" required<?= $describedBy('password', isset($errors['password']) ? '' : 'password-help') ?>>
          <?= $this->partial('partials/password-toggle', ['for' => 'password', 'confirm' => 'password_again']) ?>
          <?= $error('password') ?>
        </div>
<?php if (!isset($errors['password'])): ?>
        <div class="form-text" id="password-help">Не короче 12 символов.</div>
<?php endif ?>
      </div>

      <div class="mb-4" data-es-password-confirm-field>
        <label class="form-label" for="password_again">Пароль ещё раз</label>
        <input class="form-control<?= $field('password_again') ?>" type="password" id="password_again" name="password_again"
               autocomplete="new-password" required<?= $describedBy('password_again') ?>>
        <?= $error('password_again') ?>
      </div>

      <button class="btn btn-primary w-100" type="submit">Создать администратора</button>
    </form>

    <p class="es-meta mt-3 mb-0">То же из консоли: <code>php <?= $this->e($root) ?>/bin/estables create-admin</code></p>

<?php if ($warnings !== []): ?>
    <details class="mt-4">
      <summary class="es-meta">Предупреждения проверки сервера: <?= \count($warnings) ?></summary>
      <?= $this->partial('setup/checks', ['checks' => $warnings]) ?>
    </details>
<?php endif ?>
  </div>
</main>
