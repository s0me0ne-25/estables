<?php

/**
 * Administrator sign-in form.
 *
 * @var \Estables\Core\View    $this
 * @var string                 $formToken       CSRF token (FormGuard)
 * @var string                 $action          form address, with the page to return to
 * @var string|null            $error           why the previous attempt failed
 * @var string                 $login           previous input
 * @var bool                   $remember        previous input
 * @var int                    $rememberDays    lifetime of a remembered session
 * @var string                 $captchaTemplate widget template
 * @var array<string, mixed>   $captchaVars     widget variables
 */
?>
<main id="main" class="es-auth-page">
  <div class="es-auth-card">
    <h1 class="h2 mb-2">Вход администратора</h1>
    <p class="text-body-secondary mb-4">Только для редакторов справочника. Учётные записи создаёт владелец сайта.</p>

<?php if ($error !== null): ?>
    <div class="alert alert-danger" role="alert"><?= $this->e($error) ?></div>
<?php endif ?>

    <form method="post" action="<?= $this->e($action) ?>" novalidate>
      <input type="hidden" name="_token" value="<?= $this->e($formToken) ?>">

      <div class="mb-3">
        <label class="form-label" for="login">Логин</label>
        <input class="form-control" type="text" id="login" name="login" value="<?= $this->e($login) ?>" autocomplete="username" autocapitalize="off" spellcheck="false" required<?= $login === '' ? ' autofocus' : '' ?>>
      </div>

      <div class="mb-3">
        <label class="form-label" for="password">Пароль</label>
        <div class="input-group">
          <input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required<?= $login !== '' ? ' autofocus' : '' ?>>
          <button class="btn btn-outline-secondary" type="button" data-es-password-toggle aria-controls="password" aria-pressed="false" title="Показать пароль">
            <i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden">Показать пароль</span>
          </button>
        </div>
      </div>

      <!-- Without the mark the session cookie lives until the browser closes;
           with it, for session.remember_days, renewed on every visit. -->
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" aria-describedby="rememberHelp"<?= $remember ? ' checked' : '' ?>>
        <label class="form-check-label" for="remember">Запомнить меня</label>
        <div class="form-text mt-0" id="rememberHelp">Вход сохранится на этом устройстве на <?= $this->e(\Estables\Core\Text::plural($rememberDays, 'день', 'дня', 'дней')) ?>. Без отметки — до закрытия браузера.</div>
      </div>

      <div class="mb-4">
        <?= $this->partial($captchaTemplate, $captchaVars) ?>
      </div>

      <button class="btn btn-primary w-100" type="submit">Войти</button>
    </form>
  </div>

  <p class="mt-4 mb-0"><a href="/"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Вернуться в каталог</a></p>
</main>
