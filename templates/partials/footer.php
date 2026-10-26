<?php

/**
 * Site footer. The AGPL requires offering the source code to every user of the site.
 *
 * @var \Estables\Core\View $this
 * @var string              $sourceUrl
 */
?>
<footer class="es-footer">
  <div class="container-fluid px-3 px-lg-4 py-4 d-flex flex-wrap gap-2 column-gap-4 justify-content-between">
    <p class="mb-0">eStables — справочник коллекционера.</p>
    <p class="mb-0"><a href="<?= $this->e($sourceUrl) ?>">Исходный код</a> под лицензией AGPL-3.0</p>
  </div>
</footer>
