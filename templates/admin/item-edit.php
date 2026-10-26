<?php

/**
 * Item editor.
 *
 * @var \Estables\Core\View                                          $this
 * @var \Estables\Catalog\Item|null                                  $item     null for a new item
 * @var array{catno: string, name: string, summary: string, description: string, yearFrom: string, yearTo: string, published: bool,
 *     rubrics: list<int>, main: int, attributes: list<array{name: string, value: string}>,
 *     variants: list<array{number: string, finish: string, years: string}>} $form
 * @var array<string, string>                                        $errors   messages by field
 * @var \Estables\Catalog\RubricTree                                  $tree
 * @var list<array{id: int, label: string, path: string}>            $rubricOptions
 * @var list<string>                                                 $attributeNames
 * @var string|null                                                  $notice
 * @var string|null                                                  $publicUrl
 * @var string                                                       $csrfToken
 */
$invalid = fn(string $name): string => isset($errors[$name]) ? ' is-invalid' : '';
$error = fn(string $name): string => isset($errors[$name])
    ? '<div class="invalid-feedback d-block" id="i-' . str_replace('.', '-', $name) . '-error">' . $this->e($errors[$name]) . '</div>'
    : '';
$path = fn(int $id): string => \Estables\Http\ItemsController::rubricPath($tree, $id);
$main = \in_array($form['main'], $form['rubrics'], true) ? $form['main'] : ($form['rubrics'][0] ?? 0);
$action = $item === null ? '/admin/items/new/' : '/admin/items/' . $item->id . '/';
$variantCount = \count($form['variants']);
?>
<main id="main" class="container-xxl px-3 px-lg-4 py-4">

  <nav aria-label="Путь">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/admin/">Предметы</a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= $item === null ? 'Новый предмет' : $this->e($item->catno) ?></li>
    </ol>
  </nav>

<?php if ($notice !== null): ?>
  <div class="alert alert-success" role="status"><?= $this->e($notice) ?></div>
<?php endif ?>
<?php if ($errors !== []): ?>
  <div class="alert alert-danger" role="alert">Предмет не сохранён: проверьте поля с сообщениями.</div>
<?php endif ?>

  <div class="mb-4">
<?php if ($item !== null): ?>
    <span class="es-catno"><?= $this->e($item->catno) ?></span>
    <h1 class="mt-1 mb-0"><?= $this->e($item->name) ?></h1>
<?php else: ?>
    <h1 class="mb-0">Новый предмет</h1>
<?php endif ?>
  </div>

  <form class="row g-4" method="post" action="<?= $this->e($action) ?>" novalidate id="itemForm">
    <input type="hidden" name="_csrf" value="<?= $this->e($csrfToken) ?>">
    <input type="hidden" name="action" value="save">

    <div class="col-lg-8 d-grid gap-4">

      <!-- Main fields -->
      <section class="es-panel" aria-labelledby="sMain">
        <h2 id="sMain">Основное</h2>
        <div class="row g-3">
          <div class="col-sm-4">
            <label class="form-label" for="i-no">Номер в каталоге</label>
            <input class="form-control<?= $invalid('catno') ?>" type="text" id="i-no" name="catno" value="<?= $this->e($form['catno']) ?>" aria-describedby="i-no-help">
            <?= $error('catno') ?>
            <div class="form-text" id="i-no-help">Уникален на весь сайт. Если оставить пустым, номер будет создан при сохранении.</div>
          </div>
          <div class="col-sm-8">
            <label class="form-label" for="i-name">Название</label>
            <input class="form-control<?= $invalid('name') ?>" type="text" id="i-name" name="name" value="<?= $this->e($form['name']) ?>" required<?= $item === null ? ' autofocus' : '' ?>>
            <?= $error('name') ?>
          </div>
          <div class="col-12">
            <!-- Rubrics work like tags; the main one drives the breadcrumbs -->
            <fieldset aria-describedby="i-rubrics-help">
              <legend class="form-label fs-6">Разделы</legend>
              <ul class="es-rubric-list" data-es-rubrics>
<?php foreach ($form['rubrics'] as $rubric): ?>
<?php $isMain = $rubric === $main; ?>
                <li class="es-rubric<?= $isMain ? ' is-main' : '' ?>" data-es-rubric="<?= $rubric ?>">
                  <input type="hidden" name="rubrics[]" value="<?= $rubric ?>">
                  <span class="es-rubric-path"><?= $this->e($path($rubric)) ?></span>
                  <span class="es-status es-status-published" data-es-rubric-badge<?= $isMain ? '' : ' hidden' ?>>Главный</span>
                  <button class="btn btn-sm btn-link p-0" type="button" data-es-rubric-main<?= $isMain ? ' hidden' : '' ?>>Сделать главным</button>
                  <button class="btn btn-sm es-btn-quiet es-btn-quiet-danger ms-auto" type="button" title="Убрать из раздела" data-es-rubric-remove><i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Убрать из раздела «<?= $this->e($path($rubric)) ?>»</span></button>
                </li>
<?php endforeach ?>
              </ul>
              <!-- After the list: Bootstrap clears the floating legend with "legend + *" -->
              <input type="hidden" name="main_rubric" value="<?= $main ?>" data-es-rubric-main-input>
              <template data-es-rubric-template>
                <li class="es-rubric" data-es-rubric="">
                  <input type="hidden" name="rubrics[]" value="">
                  <span class="es-rubric-path"></span>
                  <span class="es-status es-status-published" data-es-rubric-badge hidden>Главный</span>
                  <button class="btn btn-sm btn-link p-0" type="button" data-es-rubric-main>Сделать главным</button>
                  <button class="btn btn-sm es-btn-quiet es-btn-quiet-danger ms-auto" type="button" title="Убрать из раздела" data-es-rubric-remove><i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Убрать из раздела</span></button>
                </li>
              </template>
<?php if ($rubricOptions === []): ?>
              <p class="es-meta mb-0">Разделов пока нет. Сначала создайте их в <a href="/admin/rubrics/">рубрикаторе</a>.</p>
<?php else: ?>
              <div class="input-group mt-2">
                <select class="form-select<?= $invalid('rubrics') ?>" id="i-rubric-add" aria-label="Раздел для добавления" data-es-rubric-select>
<?php foreach ($rubricOptions as $option): ?>
                  <option value="<?= $option['id'] ?>" data-es-path="<?= $this->e($option['path']) ?>"><?= $this->e($option['label']) ?></option>
<?php endforeach ?>
                </select>
                <button class="btn btn-outline-secondary" type="button" data-es-rubric-add><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Добавить раздел</button>
              </div>
<?php endif ?>
              <?= $error('rubrics') ?>
              <div class="form-text" id="i-rubrics-help">Первый добавленный раздел становится главным: от него строятся хлебные крошки и «из той же серии». В каждом новом разделе предмет встаёт в начало авторского порядка.</div>
            </fieldset>
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="i-year-from">Годы выпуска</label>
            <div class="input-group">
              <input class="form-control<?= $invalid('yearFrom') ?>" type="text" inputmode="numeric" maxlength="4" id="i-year-from" name="year_from" value="<?= $this->e($form['yearFrom']) ?>" placeholder="с" aria-describedby="i-years-help">
              <span class="input-group-text bg-transparent">–</span>
              <input class="form-control<?= $invalid('yearTo') ?>" type="text" inputmode="numeric" maxlength="4" id="i-year-to" name="year_to" value="<?= $this->e($form['yearTo']) ?>" placeholder="по" aria-label="Годы выпуска: по" aria-describedby="i-years-help">
            </div>
            <?= $error('yearFrom') ?><?= $error('yearTo') ?>
            <div class="form-text" id="i-years-help">Для фильтра по десятилетиям и сортировки по году. Можно указать только начало.</div>
          </div>
          <div class="col-12">
            <label class="form-label" for="i-lead">Краткое описание</label>
            <textarea class="form-control<?= $invalid('summary') ?>" id="i-lead" name="summary" rows="2"><?= $this->e($form['summary']) ?></textarea>
            <?= $error('summary') ?>
            <div class="form-text">Показывается в списке раздела и под заголовком предмета.</div>
          </div>
          <div class="col-12">
            <label class="form-label" for="i-text">Полное описание</label>
            <textarea class="form-control<?= $invalid('description') ?>" id="i-text" name="description" rows="7"><?= $this->e($form['description']) ?></textarea>
            <?= $error('description') ?>
          </div>
        </div>
      </section>

      <!-- Photos -->
      <section class="es-panel" aria-labelledby="sPhotos">
        <h2 id="sPhotos">Фотографии</h2>
        <p class="text-body-secondary mb-0">Загрузка фотографий появится в сборке 5. Пока на сайте вместо фото пустая подложка.</p>
      </section>

      <!-- Attributes -->
      <section class="es-panel" aria-labelledby="sAttrs">
        <h2 id="sAttrs">Характеристики</h2>
        <div class="es-rows" data-es-sortable="list" data-es-rows="attributes">
          <div class="es-row es-row-head" aria-hidden="true"><span></span><span>Характеристика</span><span>Значение</span><span></span></div>
<?php foreach ($form['attributes'] as $i => $attribute): ?>
          <div class="es-row">
            <button class="es-drag" data-es-sortable-handle type="button" title="Перетащить"><i class="bi bi-grip-vertical" aria-hidden="true"></i><span class="visually-hidden">Переместить строку</span></button>
            <input class="form-control<?= $invalid("attributes.{$i}") ?>" type="text" name="attr_name[]" list="attrNames" value="<?= $this->e($attribute['name']) ?>" aria-label="Характеристика <?= $i + 1 ?>">
            <input class="form-control" type="text" name="attr_value[]" value="<?= $this->e($attribute['value']) ?>" aria-label="Значение: <?= $this->e($attribute['name']) ?>">
            <button class="btn es-btn-quiet es-btn-quiet-danger" type="button" title="Удалить" data-es-row-remove><i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Удалить «<?= $this->e($attribute['name']) ?>»</span></button>
          </div>
<?php if (isset($errors["attributes.{$i}"])): ?>
          <div class="invalid-feedback d-block mt-0"><?= $this->e($errors["attributes.{$i}"]) ?></div>
<?php endif ?>
<?php endforeach ?>
        </div>
        <template data-es-row-template="attributes">
          <div class="es-row">
            <button class="es-drag" data-es-sortable-handle type="button" title="Перетащить"><i class="bi bi-grip-vertical" aria-hidden="true"></i><span class="visually-hidden">Переместить строку</span></button>
            <input class="form-control" type="text" name="attr_name[]" list="attrNames" aria-label="Характеристика">
            <input class="form-control" type="text" name="attr_value[]" aria-label="Значение">
            <button class="btn es-btn-quiet es-btn-quiet-danger" type="button" title="Удалить" data-es-row-remove><i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Удалить строку</span></button>
          </div>
        </template>
        <datalist id="attrNames">
<?php foreach ($attributeNames as $name): ?>
          <option value="<?= $this->e($name) ?>">
<?php endforeach ?>
        </datalist>
        <button class="btn btn-sm btn-outline-secondary mt-3" type="button" data-es-row-add="attributes"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Добавить характеристику</button>
      </section>

      <!-- Variants -->
      <section class="es-panel" aria-labelledby="sVariants">
        <h2 id="sVariants">Варианты</h2>
        <div class="es-rows" data-es-sortable="list" data-es-rows="variants">
          <div class="es-row es-row-variant es-row-head" aria-hidden="true"><span></span><span>Номер</span><span>Исполнение</span><span>Годы</span><span></span></div>
<?php foreach ($form['variants'] as $i => $variant): ?>
          <div class="es-row es-row-variant">
            <button class="es-drag" data-es-sortable-handle type="button" title="Перетащить"><i class="bi bi-grip-vertical" aria-hidden="true"></i><span class="visually-hidden">Переместить вариант</span></button>
            <input class="form-control<?= $invalid("variants.{$i}") ?>" type="text" name="var_number[]" value="<?= $this->e($variant['number']) ?>" aria-label="Номер варианта">
            <input class="form-control" type="text" name="var_finish[]" value="<?= $this->e($variant['finish']) ?>" aria-label="Исполнение варианта <?= $this->e($variant['number']) ?>">
            <input class="form-control" type="text" name="var_years[]" value="<?= $this->e($variant['years']) ?>" aria-label="Годы варианта <?= $this->e($variant['number']) ?>">
            <button class="btn es-btn-quiet es-btn-quiet-danger" type="button" title="Удалить" data-es-row-remove><i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Удалить вариант <?= $this->e($variant['number']) ?></span></button>
          </div>
<?php if (isset($errors["variants.{$i}"])): ?>
          <div class="invalid-feedback d-block mt-0"><?= $this->e($errors["variants.{$i}"]) ?></div>
<?php endif ?>
<?php endforeach ?>
        </div>
        <template data-es-row-template="variants">
          <div class="es-row es-row-variant">
            <button class="es-drag" data-es-sortable-handle type="button" title="Перетащить"><i class="bi bi-grip-vertical" aria-hidden="true"></i><span class="visually-hidden">Переместить вариант</span></button>
            <input class="form-control" type="text" name="var_number[]" aria-label="Номер варианта">
            <input class="form-control" type="text" name="var_finish[]" aria-label="Исполнение варианта">
            <input class="form-control" type="text" name="var_years[]" aria-label="Годы варианта">
            <button class="btn es-btn-quiet es-btn-quiet-danger" type="button" title="Удалить" data-es-row-remove><i class="bi bi-x-lg" aria-hidden="true"></i><span class="visually-hidden">Удалить вариант</span></button>
          </div>
        </template>
        <button class="btn btn-sm btn-outline-secondary mt-3" type="button" data-es-row-add="variants"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Добавить вариант</button>
      </section>
    </div>

    <!-- Publishing -->
    <div class="col-lg-4">
      <aside class="es-panel es-panel-sticky" aria-labelledby="sPublish">
        <h2 id="sPublish">Публикация</h2>
        <fieldset class="mb-3">
          <legend class="visually-hidden">Статус</legend>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="status" id="st-pub" value="published"<?= $form['published'] ? ' checked' : '' ?>>
            <label class="form-check-label" for="st-pub">Опубликован</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="status" id="st-draft" value="draft"<?= $form['published'] ? '' : ' checked' ?>>
            <label class="form-check-label" for="st-draft">Черновик, на сайте не виден</label>
          </div>
        </fieldset>
<?php if ($item !== null): ?>
        <dl class="small text-body-secondary mb-3">
          <dt class="fw-normal">Создан</dt><dd><?= $this->e(\Estables\Core\Text::longDate($item->createdAt)) ?></dd>
          <dt class="fw-normal">Изменён</dt><dd class="mb-0"><?= $this->e(\Estables\Core\Text::longDate($item->updatedAt)) ?></dd>
        </dl>
<?php endif ?>
        <div class="d-grid gap-2">
          <button class="btn btn-primary" type="submit"><?= $item === null ? 'Добавить предмет' : 'Сохранить' ?></button>
<?php if ($item !== null): ?>
          <a class="btn btn-outline-secondary" href="<?= $this->e($item->url()) ?>"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Открыть на сайте</a>
<?php endif ?>
        </div>
<?php if ($item !== null): ?>
        <div class="border-top mt-3 pt-3">
          <button class="btn btn-link btn-link-danger p-0" type="button" data-bs-toggle="modal" data-bs-target="#deleteItemModal"><i class="bi bi-trash me-1" aria-hidden="true"></i>Удалить предмет</button>
        </div>
<?php endif ?>
      </aside>
    </div>
  </form>
</main>

<?php if ($item !== null): ?>
<!-- Delete item -->
<div class="modal fade" id="deleteItemModal" tabindex="-1" aria-labelledby="deleteItemTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="<?= $this->e($action) ?>">
      <input type="hidden" name="_csrf" value="<?= $this->e($csrfToken) ?>">
      <input type="hidden" name="action" value="delete">
      <div class="modal-header">
        <h2 class="modal-title h4" id="deleteItemTitle">Удалить «<?= $this->e($item->name) ?>»?</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0">Предмет будет удалён из всех разделов<?= $variantCount > 0 ? ' вместе с ' . $this->e(\Estables\Core\Text::plural($variantCount, 'вариантом', 'вариантами', 'вариантами')) : '' ?>. Это действие нельзя отменить. Чтобы только убрать предмет с сайта, переведите его в черновики.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="submit" class="btn btn-danger">Удалить предмет</button>
      </div>
    </form>
  </div>
</div>
<?php endif ?>
