<?php

/**
 * Admin list of items: filters, bulk actions, the authorial order of the
 * selected rubric.
 *
 * @var \Estables\Core\View          $this
 * @var \Estables\Catalog\RubricTree  $tree
 * @var array{q: string, rubric: int, status: string, sort: string} $filter
 * @var array<string, string>        $sorts
 * @var list<array{id: int, catno: string, slug: string, name: string, summary: string, yearFrom: ?int, yearTo: ?int, published: bool,
 *     updatedAt: \DateTimeImmutable, variants: int, main: int, rubrics: list<int>}> $rows
 * @var \Estables\Core\Pager         $pager
 * @var array{total: int, drafts: int} $totals
 * @var string|null                  $notice
 * @var array<string, mixed>         $query     current filters for links
 * @var int                          $firstPosition position number of the first row
 * @var string                       $csrfToken
 */
use Estables\Core\Text;
use Estables\Http\ItemsController;

$author = $filter['sort'] === 'author' && $filter['rubric'] > 0;
$options = ItemsController::rubricOptions($tree);
$path = fn(int $id): string => ItemsController::rubricPath($tree, $id);
$back = '/admin/?' . http_build_query($query);
$pageUrl = fn(int $page): string => '/admin/?' . http_build_query(['page' => $page > 1 ? $page : null] + $query);
[$first, $last] = $pager->range();
$rubricName = $filter['rubric'] > 0 ? $tree->node($filter['rubric'])['name'] : '';
$hasRows = $rows !== [];
?>
<main id="main" class="container-xxl px-3 px-lg-4 py-4">

<?php if ($notice !== null): ?>
  <div class="alert alert-success" role="status"><?= $this->e($notice) ?></div>
<?php endif ?>
  <div class="alert alert-danger" role="alert" data-es-order-error hidden></div>

  <div class="d-flex flex-wrap gap-3 align-items-end justify-content-between mb-4">
    <div>
      <h1 class="mb-1">Предметы</h1>
      <p class="text-body-secondary mb-0"><?= $this->e(Text::number($totals['total'])) ?> <?= $this->e(preg_replace('~^\d+ ~', '', Text::plural($totals['total'], 'предмет', 'предмета', 'предметов'))) ?> в каталоге<?= $totals['drafts'] > 0 ? ', из них ' . $this->e(Text::plural($totals['drafts'], 'черновик', 'черновика', 'черновиков')) : '' ?></p>
    </div>
    <a class="btn btn-primary" href="/admin/items/new/<?= $filter['rubric'] > 0 ? '?rubric=' . $filter['rubric'] : '' ?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Добавить предмет</a>
  </div>

  <!-- Filters -->
  <form class="row g-2 align-items-end mb-3" role="search" aria-label="Фильтр предметов" method="get" action="/admin/" data-es-autosubmit>
    <div class="col-12 col-md-5 col-xl-4">
      <label class="form-label small" for="f-q">Поиск</label>
      <div class="input-group">
        <span class="input-group-text bg-transparent"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input class="form-control" type="search" id="f-q" name="q" value="<?= $this->e($filter['q']) ?>" placeholder="Название или номер">
      </div>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-xl-3">
      <label class="form-label small" for="f-rubric">Раздел</label>
      <select class="form-select" id="f-rubric" name="rubric">
        <option value="">Все разделы</option>
<?php foreach ($options as $option): ?>
        <option value="<?= $option['id'] ?>"<?= $option['id'] === $filter['rubric'] ? ' selected' : '' ?>><?= $this->e($option['label']) ?></option>
<?php endforeach ?>
      </select>
    </div>
    <div class="col-6 col-sm-3 col-md-3 col-xl-2">
      <label class="form-label small" for="f-status">Статус</label>
      <select class="form-select" id="f-status" name="status">
        <option value="">Любой</option>
        <option value="published"<?= $filter['status'] === 'published' ? ' selected' : '' ?>>Опубликован</option>
        <option value="draft"<?= $filter['status'] === 'draft' ? ' selected' : '' ?>>Черновик</option>
      </select>
    </div>
    <div class="col-6 col-sm-3 col-xl-3">
      <label class="form-label small" for="f-sort">Сортировка</label>
      <!-- "Авторский порядок" is offered only when a single rubric is selected -->
      <select class="form-select" id="f-sort" name="sort">
<?php foreach ($sorts as $key => $label): ?>
        <option value="<?= $key ?>"<?= $key === $filter['sort'] ? ' selected' : '' ?>><?= $this->e($label) ?></option>
<?php endforeach ?>
      </select>
    </div>
    <button class="visually-hidden" type="submit">Показать</button>
  </form>

<?php if ($author): ?>
  <p class="es-meta mb-3"><i class="bi bi-arrow-down-up me-1" aria-hidden="true"></i>Авторский порядок раздела «<?= $this->e($rubricName) ?>»: перетащите строку за значок, используйте стрелки в строке или клавиши ↑ ↓ на значке. Новые предметы встают в начало.</p>
<?php endif ?>

  <form id="bulkForm" method="post" action="/admin/items/bulk/">
    <input type="hidden" name="_csrf" value="<?= $this->e($csrfToken) ?>">
    <input type="hidden" name="back" value="<?= $this->e($back) ?>">
    <input type="hidden" name="rubric" value="<?= $filter['rubric'] ?>">

    <!-- Bulk actions: shown when rows are selected -->
    <div class="es-bulk mb-3" data-es-bulk hidden>
      <span>Выбрано: <strong data-es-bulk-count>0</strong></span>
      <div class="d-flex flex-wrap gap-2" role="group" aria-label="Разделы">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#addRubricModal"><i class="bi bi-folder-plus me-1" aria-hidden="true"></i>Добавить в раздел</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#removeRubricModal"><i class="bi bi-folder-minus me-1" aria-hidden="true"></i>Убрать из раздела</button>
      </div>
<?php if ($author): ?>
      <div class="d-flex flex-wrap gap-2" role="group" aria-label="Порядок в разделе">
        <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="top"><i class="bi bi-chevron-bar-up me-1" aria-hidden="true"></i>В начало</button>
        <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="bottom"><i class="bi bi-chevron-bar-down me-1" aria-hidden="true"></i>В конец</button>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#positionModal"><i class="bi bi-123 me-1" aria-hidden="true"></i>На позицию…</button>
      </div>
<?php endif ?>
      <div class="d-flex flex-wrap gap-2" role="group" aria-label="Публикация">
        <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="publish"><i class="bi bi-eye me-1" aria-hidden="true"></i>Опубликовать</button>
        <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="unpublish"><i class="bi bi-eye-slash me-1" aria-hidden="true"></i>Снять с публикации</button>
        <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteItemsModal"><i class="bi bi-trash me-1" aria-hidden="true"></i>Удалить</button>
      </div>
      <button class="btn btn-sm btn-link ms-auto" type="button" data-es-bulk-clear>Снять выделение</button>
    </div>

    <div class="table-responsive border-top">
      <table class="table table-hover es-admin-table">
        <thead>
          <tr>
            <th scope="col" class="ps-2 es-col-check">
              <input class="form-check-input" type="checkbox" data-es-select-all aria-label="Выбрать все на странице"<?= $hasRows ? '' : ' disabled' ?>>
            </th>
<?php if ($author): ?>
            <th scope="col" class="es-col-order">Порядок</th>
<?php endif ?>
            <th scope="col" class="es-col-thumb"><span class="visually-hidden">Фото</span></th>
            <th scope="col">Номер</th>
            <th scope="col">Название и разделы</th>
            <th scope="col">Годы</th>
            <th scope="col">Фото</th>
            <th scope="col">Статус</th>
            <th scope="col">Изменён</th>
            <th scope="col" class="text-end">Действия</th>
          </tr>
        </thead>
        <tbody<?php if ($author): ?> data-es-order data-es-sortable="list" data-es-order-url="/admin/items/order/" data-es-order-rubric="<?= $filter['rubric'] ?>" data-es-csrf="<?= $this->e($csrfToken) ?>"<?php endif ?>>
<?php if (!$hasRows): ?>
          <tr><td colspan="<?= $author ? 10 : 9 ?>" class="text-body-secondary py-4"><?= $filter['q'] !== '' || $filter['rubric'] > 0 || $filter['status'] !== '' ? 'Ничего не нашлось. Измените условия фильтра.' : 'Предметов пока нет. Начните с кнопки «Добавить предмет».' ?></td></tr>
<?php endif ?>
<?php foreach ($rows as $n => $row):
    $catno = $this->e($row['catno']);
    $others = array_values(array_filter($row['rubrics'], static fn(int $r): bool => $r !== $row['main']));
?>
          <tr<?= $author ? ' data-es-order-row data-es-item="' . $row['id'] . '"' : '' ?>>
            <td class="ps-2"><input class="form-check-input" type="checkbox" name="ids[]" value="<?= $row['id'] ?>" data-es-select aria-label="Выбрать <?= $catno ?>"></td>
<?php if ($author): ?>
            <td class="text-nowrap"><button class="es-drag" data-es-sortable-handle type="button" title="Перетащить"><i class="bi bi-grip-vertical" aria-hidden="true"></i><span class="visually-hidden">Переместить <?= $catno ?></span></button><span class="es-position" data-es-order-pos><?= $firstPosition + $n ?></span></td>
<?php endif ?>
            <td><span class="es-thumb es-thumb-empty" aria-hidden="true"></span></td>
            <td class="es-catno"><?= $catno ?></td>
            <td class="es-col-title"><a class="es-title" href="/admin/items/<?= $row['id'] ?>/"><?= $this->e($row['name']) ?></a><span class="es-path"><?= $this->e($path($row['main'])) ?><?php if ($others !== []):
                $titles = implode('; ', array_map($path, $others)); ?> <span class="es-more" title="Также: <?= $this->e($titles) ?>">+<?= \count($others) ?><span class="visually-hidden"> <?= $this->e(preg_replace('~^\d+ ~', '', Text::plural(\count($others), 'раздел', 'раздела', 'разделов'))) ?>: <?= $this->e($titles) ?></span></span><?php endif ?></span></td>
            <td class="text-nowrap"><?= $this->e(ItemsController::years($row['yearFrom'], $row['yearTo'])) ?></td>
            <td>0</td>
            <td><?= $row['published'] ? '<span class="es-status es-status-published">Опубликован</span>' : '<span class="es-status es-status-draft">Черновик</span>' ?></td>
            <td class="text-nowrap small text-body-secondary"><?= $this->e($this->dateTime($row['updatedAt'], 'd.m.Y')) ?></td>
            <td class="text-end text-nowrap">
<?php if ($author): ?>
              <button class="btn btn-sm es-btn-quiet" type="button" title="Выше" data-es-order-move="up"><i class="bi bi-arrow-up" aria-hidden="true"></i><span class="visually-hidden">Поднять <?= $catno ?></span></button>
              <button class="btn btn-sm es-btn-quiet" type="button" title="Ниже" data-es-order-move="down"><i class="bi bi-arrow-down" aria-hidden="true"></i><span class="visually-hidden">Опустить <?= $catno ?></span></button>
<?php endif ?>
              <a class="btn btn-sm btn-outline-secondary" href="/admin/items/<?= $row['id'] ?>/" title="Редактировать"><i class="bi bi-pencil" aria-hidden="true"></i><span class="visually-hidden">Редактировать <?= $catno ?></span></a>
              <a class="btn btn-sm btn-outline-secondary" href="/item/<?= $this->e($row['slug']) ?>/" title="Открыть на сайте"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span class="visually-hidden">Открыть <?= $catno ?> на сайте</span></a>
            </td>
          </tr>
<?php endforeach ?>
        </tbody>
      </table>
    </div>
  </form>

  <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mt-4">
    <span class="text-body-secondary small"><?= $pager->total > 0 ? "Показаны {$first}–{$last} из " . $this->e(Text::number($pager->total)) : 'Нет предметов' ?></span>
<?= $this->partial('partials/pagination', ['pager' => $pager, 'pageUrl' => $pageUrl, 'label' => 'Страницы списка']) ?>
  </div>
</main>

<!-- Add selected items to a rubric -->
<div class="modal fade" id="addRubricModal" tabindex="-1" aria-labelledby="addRubricModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h4" id="addRubricModalTitle">Добавить в раздел</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="addRubricTarget">Раздел</label>
        <select class="form-select" id="addRubricTarget" name="target_add" form="bulkForm">
<?php foreach ($options as $option): ?>
          <option value="<?= $option['id'] ?>"><?= $this->e($option['label']) ?></option>
<?php endforeach ?>
        </select>
        <p class="form-text mb-0">Предметы останутся и в своих нынешних разделах; главный раздел у них не изменится. В новом разделе они встанут в начало авторского порядка.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="submit" class="btn btn-primary" form="bulkForm" name="action" value="add-rubric">Добавить</button>
      </div>
    </div>
  </div>
</div>

<!-- Remove selected items from a rubric -->
<div class="modal fade" id="removeRubricModal" tabindex="-1" aria-labelledby="removeRubricModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h4" id="removeRubricModalTitle">Убрать из раздела</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="removeRubricTarget">Раздел</label>
        <select class="form-select" id="removeRubricTarget" name="target_remove" form="bulkForm">
<?php foreach ($options as $option): ?>
          <option value="<?= $option['id'] ?>"<?= $option['id'] === $filter['rubric'] ? ' selected' : '' ?>><?= $this->e($option['label']) ?></option>
<?php endforeach ?>
        </select>
        <p class="form-text mb-0">Если раздел у предмета единственный, предмет в нём останется: у каждого предмета должен быть хотя бы один раздел. Если убран главный раздел, главным станет следующий по дате добавления.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="submit" class="btn btn-primary" form="bulkForm" name="action" value="remove-rubric">Убрать</button>
      </div>
    </div>
  </div>
</div>

<?php if ($author):
    $count = $tree->has($filter['rubric']) ? $pager->total : 0; ?>
<!-- Move selected items to a position in the authorial order -->
<div class="modal fade" id="positionModal" tabindex="-1" aria-labelledby="positionModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h4" id="positionModalTitle">Поставить на позицию</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <label class="form-label" for="positionTarget">Позиция в разделе «<?= $this->e($rubricName) ?>»</label>
        <input class="form-control" type="number" id="positionTarget" name="position" form="bulkForm" min="1" max="<?= max(1, $count) ?>" value="1">
        <p class="form-text mb-0">От 1 до <?= max(1, $count) ?>. Выбранные предметы встанут подряд, начиная с этой позиции, в нынешнем взаимном порядке.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="submit" class="btn btn-primary" form="bulkForm" name="action" value="position">Переставить</button>
      </div>
    </div>
  </div>
</div>
<?php endif ?>

<!-- Delete items -->
<div class="modal fade" id="deleteItemsModal" tabindex="-1" aria-labelledby="deleteItemsModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title h4" id="deleteItemsModalTitle">Удалить предметы?</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">Предметы будут удалены из всех разделов вместе с вариантами. Это действие нельзя отменить. Чтобы только убрать предметы с сайта, снимите их с публикации.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="submit" class="btn btn-danger" form="bulkForm" name="action" value="delete">Удалить</button>
      </div>
    </div>
  </div>
</div>
