<?php

/**
 * Cloudflare Turnstile widget; the script fills in the cf-turnstile-response field.
 *
 * @var \Estables\Core\View $this
 * @var string              $siteKey
 * @var string              $scriptUrl
 */
?>
<div class="cf-turnstile" data-sitekey="<?= $this->e($siteKey) ?>" data-language="ru" data-size="flexible"></div>
<script src="<?= $this->e($scriptUrl) ?>" async defer></script>
<noscript><div class="form-text">Для входа включите JavaScript: без него проверка Cloudflare не работает.</div></noscript>
