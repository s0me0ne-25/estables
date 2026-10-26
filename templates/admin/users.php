<?php

/**
 * Admin page "Users": administrators, the current user's sessions, and the
 * modal forms "Add administrator" and "Change password".
 *
 * @var \Estables\Core\View                                    $this
 * @var list<array{user: \Estables\Auth\User, sessions: int}>  $users
 * @var list<array{handle: string, ip: string, userAgent: string, createdAt: \DateTimeImmutable, lastSeenAt: \DateTimeImmutable, expiresAt: \DateTimeImmutable, remember: bool}> $sessions
 * @var \Estables\Auth\Session                                 $me
 * @var string|null                                            $notice    result of the previous action
 * @var array<string, string>                                  $errors    messages by field, for $openModal
 * @var array<string, string>                                  $old       previous input of $openModal
 * @var string|null                                            $openModal modal to reopen after a failed submission
 * @var int                                                    $minPassword
 * @var int                                                    $idleHours
 * @var string                                                 $csrfToken
 */
$csrf = '<input type="hidden" name="_csrf" value="' . $this->e($csrfToken) . '">';

// Validation state of a field in the modal that is being reopened
$invalid = function (string $modal, string $name) use ($errors, $openModal): string {
    return $modal === $openModal && isset($errors[$name]) ? ' is-invalid' : '';
};
// $id lets the message take the place of the field's hint for aria-describedby
$error = function (string $modal, string $name, string $id = '') use ($errors, $openModal): string {
    return $modal === $openModal && isset($errors[$name])
        ? '<div class="invalid-feedback"' . ($id !== '' ? ' id="' . $id . '"' : '') . '>' . $this->e($errors[$name]) . '</div>'
        : '';
};
$value = function (string $modal, string $name) use ($old, $openModal): string {
    return $modal === $openModal ? $this->e($old[$name] ?? '') : '';
};
?>
<main id="main" class="container-xxl px-3 px-lg-4 py-4"<?= $openModal !== null ? ' data-es-modal-open="' . $this->e($openModal) . '"' : '' ?>>

<?php if ($notice !== null): ?>
  <div class="alert alert-success" role="status"><?= $this->e($notice) ?></div>
<?php endif ?>

  <div class="d-flex flex-wrap gap-3 align-items-end justify-content-between mb-4">
    <div>
      <h1 class="mb-1">Пользователи</h1>
      <p class="text-body-secondary mb-0">Все администраторы равноправны. Свободной регистрации нет: новых добавляют здесь или командой <code>bin/estables create-admin</code>.</p>
    </div>
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Добавить администратора</button>
  </div>

  <div class="table-responsive border-top mb-5">
    <table class="table es-admin-table">
      <thead>
        <tr>
          <th scope="col">Логин</th>
          <th scope="col">Имя</th>
          <th scope="col">Последний вход</th>
          <th scope="col">Сессий</th>
          <th scope="col">Статус</th>
          <th scope="col" class="text-end">Действия</th>
        </tr>
      </thead>
      <tbody>
<?php foreach ($users as ['user' => $user, 'sessions' => $count]): ?>
<?php $self = $user->id === $me->user->id; ?>
        <tr>
          <td class="fw-semibold"><?= $this->e($user->login) ?><?php if ($self): ?> <span class="es-meta d-inline">(вы)</span><?php endif ?></td>
          <td><?= $this->e($user->displayName) ?></td>
          <td class="text-nowrap small text-body-secondary"><?= $user->lastLoginAt !== null ? $this->e($this->dateTime($user->lastLoginAt)) : '—' ?></td>
          <td><?= $count ?></td>
          <td><?= $user->active ? '<span class="es-status es-status-published">Активен</span>' : '<span class="es-status es-status-draft">Заблокирован</span>' ?></td>
          <td class="text-end text-nowrap">
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#passwordModal-<?= $user->id ?>"><i class="bi bi-key me-1" aria-hidden="true"></i>Сменить пароль</button>
            <form class="d-inline" method="post" action="/admin/users/" data-es-confirm="<?= $this->e($self ? 'Завершить все ваши сессии, включая эту? Потребуется войти заново.' : 'Завершить все сессии «' . $user->login . '»?') ?>">
              <?= $csrf ?><input type="hidden" name="action" value="end-sessions"><input type="hidden" name="user" value="<?= $user->id ?>">
              <button class="btn btn-sm btn-outline-secondary" type="submit"<?= $count === 0 ? ' disabled' : '' ?>><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Завершить сессии</button>
            </form>
<?php if (!$self): ?>
            <form class="d-inline" method="post" action="/admin/users/"<?= $user->active ? ' data-es-confirm="' . $this->e('Заблокировать «' . $user->login . '»? Все его сессии будут завершены.') . '"' : '' ?>>
              <?= $csrf ?><input type="hidden" name="action" value="<?= $user->active ? 'block' : 'unblock' ?>"><input type="hidden" name="user" value="<?= $user->id ?>">
<?php if ($user->active): ?>
              <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-lock me-1" aria-hidden="true"></i>Заблокировать</button>
<?php else: ?>
              <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-unlock me-1" aria-hidden="true"></i>Разблокировать</button>
<?php endif ?>
            </form>
<?php endif ?>
          </td>
        </tr>
<?php endforeach ?>
      </tbody>
    </table>
  </div>

  <section aria-labelledby="sessionsTitle">
    <div class="d-flex flex-wrap gap-3 align-items-end justify-content-between mb-3">
      <div>
        <h2 id="sessionsTitle" class="mb-1">Мои активные сессии</h2>
        <p class="es-meta">Сессия «до закрытия браузера» дополнительно завершается после <?= $this->e(\Estables\Core\Text::plural($idleHours, 'часа', 'часов', 'часов')) ?> без активности. Запомненная продлевается при каждом заходе.</p>
      </div>
      <form method="post" action="/admin/users/">
        <?= $csrf ?><input type="hidden" name="action" value="end-others">
        <button class="btn btn-outline-secondary" type="submit"<?= \count($sessions) < 2 ? ' disabled' : '' ?>><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Завершить все, кроме этой</button>
      </form>
    </div>
    <div class="table-responsive border-top">
      <table class="table es-admin-table">
        <thead>
          <tr>
            <th scope="col">Браузер и система</th>
            <th scope="col">IP</th>
            <th scope="col">Вход</th>
            <th scope="col">Активность</th>
            <th scope="col">Срок</th>
            <th scope="col" class="text-end"><span class="visually-hidden">Действия</span></th>
          </tr>
        </thead>
        <tbody>
<?php foreach ($sessions as $session): ?>
<?php
    $agent = \Estables\Auth\UserAgent::parse($session['userAgent']);
    $current = $session['handle'] === $me->handle();
?>
        <tr>
          <td><i class="bi bi-<?= $agent->icon ?> me-2 text-body-secondary" aria-hidden="true"></i><?= $this->e($agent->label()) ?></td>
          <td class="text-nowrap"><?= $this->e($session['ip']) ?></td>
          <td class="text-nowrap small text-body-secondary"><?= $this->e($this->dateTime($session['createdAt'])) ?></td>
          <td class="text-nowrap small text-body-secondary"><?= $current ? 'только что' : $this->e($this->dateTime($session['lastSeenAt'])) ?></td>
          <td class="small"><?= $session['remember'] ? 'запомнена до ' . $this->e($this->dateTime($session['expiresAt'], 'd.m.Y')) : 'до закрытия браузера' ?></td>
          <td class="text-end text-nowrap">
<?php if ($current): ?>
            <span class="es-meta d-inline">это устройство</span>
<?php else: ?>
            <form method="post" action="/admin/users/">
              <?= $csrf ?><input type="hidden" name="action" value="end-session"><input type="hidden" name="session" value="<?= $this->e($session['handle']) ?>">
              <button class="btn btn-sm btn-outline-secondary" type="submit">Завершить</button>
            </form>
<?php endif ?>
          </td>
        </tr>
<?php endforeach ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

<!-- Add administrator -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="/admin/users/" novalidate>
      <?= $csrf ?><input type="hidden" name="action" value="add">
      <div class="modal-header">
        <h2 class="modal-title h4" id="addUserModalTitle">Добавить администратора</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label" for="u-login">Логин</label>
          <input class="form-control<?= $invalid('addUserModal', 'login') ?>" type="text" id="u-login" name="login" value="<?= $value('addUserModal', 'login') ?>" autocomplete="off" autocapitalize="off" spellcheck="false" required>
          <?= $error('addUserModal', 'login') ?>
        </div>
        <div class="mb-3">
          <label class="form-label" for="u-name">Имя</label>
          <input class="form-control<?= $invalid('addUserModal', 'display_name') ?>" type="text" id="u-name" name="display_name" value="<?= $value('addUserModal', 'display_name') ?>">
          <?= $error('addUserModal', 'display_name') ?>
        </div>
        <div class="mb-3">
          <label class="form-label" for="u-pass">Пароль</label>
          <input class="form-control<?= $invalid('addUserModal', 'password') ?>" type="password" id="u-pass" name="password" autocomplete="new-password" aria-describedby="u-pass-help" required>
          <?= $error('addUserModal', 'password', 'u-pass-help') ?>
<?php if ($invalid('addUserModal', 'password') === ''): ?>
          <div class="form-text" id="u-pass-help">Не короче <?= $minPassword ?> символов.</div>
<?php endif ?>
        </div>
        <div>
          <label class="form-label" for="u-pass2">Пароль ещё раз</label>
          <input class="form-control<?= $invalid('addUserModal', 'password_again') ?>" type="password" id="u-pass2" name="password_again" autocomplete="new-password" required>
          <?= $error('addUserModal', 'password_again') ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="submit" class="btn btn-primary">Добавить</button>
      </div>
    </form>
  </div>
</div>

<?php foreach ($users as ['user' => $user]): ?>
<?php
    $self = $user->id === $me->user->id;
    $modal = 'passwordModal-' . $user->id;
    $p = 'p' . $user->id . '-';
    $endSessions = $modal !== $openModal || ($old['end_sessions'] ?? '1') === '1';
?>
<!-- Change password of <?= $this->e($user->login) ?> -->
<div class="modal fade" id="<?= $modal ?>" tabindex="-1" aria-labelledby="<?= $modal ?>-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="/admin/users/" novalidate>
      <?= $csrf ?><input type="hidden" name="action" value="password"><input type="hidden" name="user" value="<?= $user->id ?>">
      <!-- Lets password managers know whose password this is -->
      <input type="text" name="username" value="<?= $this->e($user->login) ?>" autocomplete="username" hidden>
      <div class="modal-header">
        <h2 class="modal-title h4" id="<?= $modal ?>-title">Сменить пароль<?= $self ? '' : ': ' . $this->e($user->login) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
<?php if ($self): ?>
        <div class="mb-3">
          <label class="form-label" for="<?= $p ?>current">Текущий пароль</label>
          <input class="form-control<?= $invalid($modal, 'current_password') ?>" type="password" id="<?= $p ?>current" name="current_password" autocomplete="current-password" required>
          <?= $error($modal, 'current_password') ?>
        </div>
<?php endif ?>
        <div class="mb-3">
          <label class="form-label" for="<?= $p ?>new">Новый пароль</label>
          <input class="form-control<?= $invalid($modal, 'password') ?>" type="password" id="<?= $p ?>new" name="password" autocomplete="new-password" aria-describedby="<?= $p ?>new-help" required>
          <?= $error($modal, 'password', $p . 'new-help') ?>
<?php if ($invalid($modal, 'password') === ''): ?>
          <div class="form-text" id="<?= $p ?>new-help">Не короче <?= $minPassword ?> символов.</div>
<?php endif ?>
        </div>
        <div class="mb-3">
          <label class="form-label" for="<?= $p ?>new2">Новый пароль ещё раз</label>
          <input class="form-control<?= $invalid($modal, 'password_again') ?>" type="password" id="<?= $p ?>new2" name="password_again" autocomplete="new-password" required>
          <?= $error($modal, 'password_again') ?>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="<?= $p ?>logout" name="end_sessions" value="1"<?= $endSessions ? ' checked' : '' ?>>
          <label class="form-check-label" for="<?= $p ?>logout"><?= $self ? 'Завершить остальные мои сессии' : 'Завершить все сессии этого пользователя' ?></label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="submit" class="btn btn-primary">Сменить пароль</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach ?>
