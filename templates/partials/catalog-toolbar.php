<?php

/**
 * Toolbar above a list of items: the rubricator button for phones, the
 * shown range, the sort order and the grid/list switch.
 *
 * @var \Estables\Core\View    $this
 * @var \Estables\Core\Pager   $pager
 * @var string                 $sort
 * @var array<string, string>  $sorts
 */
[$first, $last] = $pager->range();
?>
      <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between border-top border-bottom py-2 mb-4">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-secondary btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#rubricator" aria-controls="rubricator">
            <i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>Рубрикатор
          </button>
          <span class="text-body-secondary small">Показаны <?= $first ?>–<?= $last ?> из <?= $this->e(\Estables\Core\Text::number($pager->total)) ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <label class="visually-hidden" for="sort">Сортировка</label>
          <!-- The visitor's choice is remembered site-wide in the es_sort cookie -->
          <select class="form-select form-select-sm w-auto" id="sort" data-es-sort>
<?php foreach ($sorts as $key => $label): ?>
            <option value="<?= $key ?>"<?= $key === $sort ? ' selected' : '' ?>><?= $this->e($label) ?></option>
<?php endforeach ?>
          </select>
          <div class="btn-group btn-group-sm" role="group" aria-label="Вид списка">
            <button class="btn btn-outline-secondary active" type="button" data-es-view="grid" aria-pressed="true" title="Плитка"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i><span class="visually-hidden">Плитка</span></button>
            <button class="btn btn-outline-secondary" type="button" data-es-view="list" aria-pressed="false" title="Список"><i class="bi bi-list-ul" aria-hidden="true"></i><span class="visually-hidden">Список</span></button>
          </div>
        </div>
      </div>
