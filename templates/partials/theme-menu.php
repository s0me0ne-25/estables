<?php

/**
 * Colour theme switcher (light / dark / system); see initThemeSwitcher in estables.js.
 *
 * @var \Estables\Core\View $this
 */
?>
<div class="dropdown">
  <button class="btn btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Оформление">
    <i class="bi bi-sun" data-es-theme-icon aria-hidden="true"></i>
  </button>
  <ul class="dropdown-menu dropdown-menu-end">
    <li><button class="dropdown-item" type="button" data-es-theme-value="light"><i class="bi bi-sun me-2" aria-hidden="true"></i>Светлое</button></li>
    <li><button class="dropdown-item" type="button" data-es-theme-value="dark"><i class="bi bi-moon-stars me-2" aria-hidden="true"></i>Тёмное</button></li>
    <li><button class="dropdown-item" type="button" data-es-theme-value="auto"><i class="bi bi-circle-half me-2" aria-hidden="true"></i>Как в системе</button></li>
  </ul>
</div>
