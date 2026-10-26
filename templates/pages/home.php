<?php

/**
 * Catalogue root. Build 1: the catalogue is always empty.
 *
 * @var \Estables\Core\View $this
 */
?>
<div class="es-layout">

    <!-- ======================= Rubricator ======================= -->
    <aside class="es-aside">
      <div class="offcanvas-lg offcanvas-start es-sidebar" tabindex="-1" id="rubricator" aria-labelledby="rubricatorTitle">
        <div class="offcanvas-header">
          <h2 class="offcanvas-title es-sidebar-title mb-0" id="rubricatorTitle">Рубрикатор</h2>
          <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#rubricator" aria-label="Закрыть"></button>
        </div>
        <div class="offcanvas-body d-block p-3 p-lg-4">
          <h2 class="es-sidebar-title d-none d-lg-block">Рубрикатор</h2>
          <p class="es-meta">Разделов пока нет.</p>
        </div>
      </div>
    </aside>

    <!-- ======================= Main content ======================= -->
    <main id="main" class="es-main px-3 px-lg-4 px-xxl-5 py-4">

      <nav aria-label="Путь по разделам">
        <ol class="breadcrumb">
          <li class="breadcrumb-item active" aria-current="page">Каталог</li>
        </ol>
      </nav>

      <h1 class="mb-3">Каталог</h1>
      <p class="es-lead">Справочник пока пуст. Разделы и предметы появятся здесь, когда администратор их добавит.</p>
    </main>
</div>
