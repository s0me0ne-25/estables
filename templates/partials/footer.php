<?php

/**
 * Site footer. The AGPL requires offering the source code to every user of the site.
 *
 * @var \Estables\Core\View $this
 * @var string              $sourceUrl
 * @var string|null         $authState "guest", "admin", or null before installation
 * @var string              $csrfToken token for the sign-out form
 */
$authState ??= null;
$csrfToken ??= '';
?>
<footer class="es-footer">
  <div class="container-fluid px-3 px-lg-4 py-4 d-flex flex-wrap gap-2 column-gap-4 justify-content-between">
    <p class="mb-0">
      eStables — справочник коллекционера.
<?php if ($authState === 'guest'): ?>
      <span data-es-auth="guest"><a class="es-quiet-link" href="/login/">Вход администратора</a></span>
<?php elseif ($authState === 'admin'): ?>
      <span data-es-auth="admin">Вы — администратор (<a href="/admin/">панель управления</a> | <button class="es-link-button" type="submit" form="es-logout">выход</button>)</span>
<?php endif ?>
    </p>
<?php if ($authState === 'admin'): ?>
    <!-- Sign-out changes state, so it is a POST with the CSRF token; a form cannot sit inside <p>. -->
    <form id="es-logout" method="post" action="/logout/" hidden><input type="hidden" name="_csrf" value="<?= $this->e($csrfToken) ?>"></form>
<?php endif ?>
    <p class="mb-0"><a href="<?= $this->e($sourceUrl) ?>">Исходный код</a> под лицензией AGPL-3.0</p>
  </div>
</footer>
