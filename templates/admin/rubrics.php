<?php

/**
 * Admin page "Rubricator": the tree and the form of the selected or new rubric.
 *
 * @var \Estables\Core\View                 $this
 * @var \Estables\Catalog\RubricTree         $tree
 * @var \Estables\Catalog\Rubric|null        $rubric    rubric being edited
 * @var bool                                $creating  the form adds a new rubric
 * @var array{parent: int, name: string, title: string, slug: string, description: string, visible: bool}|null $form
 * @var array<string, string>               $errors    messages by field
 * @var string|null                         $failure   why the last action failed
 * @var string|null                         $notice    result of the last action
 * @var array<int, true>                    $open      expanded branches
 * @var list<array{id: int, label: string, prefix: string}> $parents
 * @var array<int, array{items: int, shared: int}> $stats items linked directly, and how many of them have other rubrics
 * @var list<array{id: int, label: string, path: string}> $targets rubrics to move items to
 * @var string                              $csrfToken
 * @var \Estables\Media\MediaStore          $media
 * @var \Estables\Media\ImageRepository     $images
 */
$csrf = '<input type="hidden" name="_csrf" value="' . $this->e($csrfToken) . '">';
$invalid = fn(string $name): string => isset($errors[$name]) ? ' is-invalid' : '';
$error = fn(string $name): string => isset($errors[$name])
    ? '<div class="invalid-feedback" id="r-' . $name . '-error">' . $this->e($errors[$name]) . '</div>'
    : '';
$selected = $rubric?->id ?? 0;
$prefix = '/catalog/';
foreach ($parents as $option) {
    if ($form !== null && $option['id'] === $form['parent']) {
        $prefix = $option['prefix'];
    }
}
$children = $rubric !== null ? \count($tree->children($rubric->id)) : 0;
?>
<main id="main" class="container-xxl px-3 px-lg-4 py-4">

<?php if ($notice !== null): ?>
  <div class="alert alert-success" role="status"><?= $this->e($notice) ?></div>
<?php endif ?>
<?php if ($failure !== null): ?>
  <div class="alert alert-danger" role="alert"><?= $this->e($failure) ?></div>
<?php endif ?>
  <div class="alert alert-danger" role="alert" data-es-rubric-move-error hidden></div>

  <div class="d-flex flex-wrap gap-3 align-items-end justify-content-between mb-4">
    <div>
      <h1 class="mb-1">Рубрикатор</h1>
      <p class="text-body-secondary mb-0">Разделы каталога и их порядок. Перетащите раздел за значок слева, чтобы изменить порядок или вложенность. С клавиатуры: на значке клавиши ↑ ↓ меняют порядок, ← → — вложенность.</p>
    </div>
    <a class="btn btn-primary" href="/admin/rubrics/?new=1"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Добавить раздел</a>
  </div>

  <div class="row g-4">

    <!-- Tree -->
    <div class="col-lg-5">
      <section class="es-panel" aria-labelledby="treeTitle">
        <h2 id="treeTitle">Разделы</h2>
<?php if ($tree->isEmpty()): ?>
        <p class="es-meta mb-0">Разделов пока нет. Добавьте первый с помощью формы.</p>
<?php else: ?>
        <ul class="es-tree es-tree-admin" data-es-sortable="tree" data-es-rubric-tree data-es-move-url="/admin/rubrics/move/" data-es-csrf="<?= $this->e($csrfToken) ?>">
<?= $this->partial('admin/rubric-branch', ['tree' => $tree, 'parent' => 0, 'selected' => $selected, 'open' => $open, 'stats' => $stats]) ?>
        </ul>
<?php endif ?>
      </section>
    </div>

    <!-- Selected rubric -->
    <div class="col-lg-7">
<?php if ($form === null): ?>
      <section class="es-panel">
        <h2>Раздел не выбран</h2>
        <p class="text-body-secondary mb-0">Выберите раздел в дереве слева, чтобы изменить его, или добавьте новый.</p>
      </section>
<?php else: ?>
      <section class="es-panel" aria-labelledby="rubricTitle">
        <div class="d-flex flex-wrap gap-2 align-items-baseline justify-content-between mb-3">
          <h2 id="rubricTitle" class="mb-0"><?= $rubric !== null ? $this->e($rubric->name) : 'Новый раздел' ?></h2>
<?php if ($rubric !== null): ?>
          <a class="small" href="<?= $this->e($tree->url($rubric->id)) ?>"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Открыть на сайте</a>
<?php endif ?>
        </div>

        <form method="post" action="/admin/rubrics/" enctype="multipart/form-data" novalidate>
          <?= $csrf ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= $selected ?>">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="r-name">Название в рубрикаторе</label>
              <input class="form-control<?= $invalid('name') ?>" type="text" id="r-name" name="name" value="<?= $this->e($form['name']) ?>" required<?= $creating ? ' autofocus' : '' ?>>
              <?= $error('name') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="r-title">Заголовок страницы</label>
              <input class="form-control<?= $invalid('title') ?>" type="text" id="r-title" name="title" value="<?= $this->e($form['title']) ?>">
              <?= $error('title') ?>
              <div class="form-text">Если пусто, используется название.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="r-parent">Родительский раздел</label>
              <select class="form-select<?= $invalid('parent') ?>" id="r-parent" name="parent" data-es-slug-parent>
<?php foreach ($parents as $option): ?>
                <option value="<?= $option['id'] ?>" data-es-prefix="<?= $this->e($option['prefix']) ?>"<?= $option['id'] === $form['parent'] ? ' selected' : '' ?>><?= $this->e($option['label']) ?></option>
<?php endforeach ?>
              </select>
              <?= $error('parent') ?>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="r-slug">Адрес страницы</label>
              <div class="input-group has-validation">
                <span class="input-group-text small text-body-secondary bg-transparent" data-es-slug-prefix><?= $this->e($prefix) ?></span>
                <input class="form-control<?= $invalid('slug') ?>" type="text" id="r-slug" name="slug" value="<?= $this->e($form['slug']) ?>" spellcheck="false" autocapitalize="off" aria-describedby="r-slug-help">
                <?= $error('slug') ?>
              </div>
              <div class="form-text" id="r-slug-help">Латиница, цифры и дефисы. Если пусто, адрес получится из названия.<?= $rubric !== null ? ' Старый адрес будет перенаправлять на новый.' : '' ?></div>
            </div>

            <div class="col-12">
              <label class="form-label" for="r-desc">Описание</label>
              <textarea class="form-control<?= $invalid('description') ?>" id="r-desc" name="description" rows="4"><?= $this->e($form['description']) ?></textarea>
              <?= $error('description') ?>
              <div class="form-text">Показывается под заголовком раздела. Пустая строка начинает новый абзац.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label" for="r-cover">Обложка раздела</label>
<?php if ($rubric?->cover !== null && ($coverImage = $images->find($rubric->cover)) !== null): ?>
              <div class="d-flex align-items-center gap-2 mb-2">
                <img class="es-cover-preview" src="<?= $this->e($media->thumbUrl($coverImage)) ?>" alt="Текущая обложка">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="r-cover-remove" name="remove_cover" value="1">
                  <label class="form-check-label" for="r-cover-remove">Убрать обложку</label>
                </div>
              </div>
<?php endif ?>
              <input class="form-control<?= $invalid('cover') ?>" type="file" id="r-cover" name="cover" accept="image/*">
              <?= $error('cover') ?>
              <div class="form-text">Необязательно. Без обложки берётся фото первого предмета в авторском порядке.</div>
            </div>

            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="r-visible" name="visible" value="1"<?= $form['visible'] ? ' checked' : '' ?>>
                <label class="form-check-label" for="r-visible">Показывать раздел на сайте</label>
              </div>
            </div>
          </div>

<?php if ($rubric !== null): ?>
          <p class="text-body-secondary small mt-3 mb-0">В разделе <?= $this->e(\Estables\Core\Text::plural($stats[$rubric->id]['items'] ?? 0, 'предмет', 'предмета', 'предметов')) ?>, <?= $children === 0 ? 'подразделов нет' : 'подразделов: ' . $children ?>.</p>
<?php endif ?>

          <div class="d-flex flex-wrap gap-2 border-top pt-3 mt-3">
<?php if ($rubric !== null): ?>
            <button class="btn btn-primary" type="submit">Сохранить</button>
            <button class="btn btn-outline-secondary" type="reset">Отменить изменения</button>
            <button class="btn btn-outline-danger ms-sm-auto" type="button" data-bs-toggle="modal" data-bs-target="#deleteRubricModal" data-es-rubric-delete="<?= $rubric->id ?>" data-es-rubric-name="<?= $this->e($rubric->name) ?>" data-es-rubric-children="<?= $children ?>" data-es-rubric-items="<?= $stats[$rubric->id]['items'] ?? 0 ?>" data-es-rubric-shared="<?= $stats[$rubric->id]['shared'] ?? 0 ?>"><i class="bi bi-trash me-1" aria-hidden="true"></i>Удалить раздел</button>
<?php else: ?>
            <button class="btn btn-primary" type="submit">Добавить раздел</button>
<?php if (!$tree->isEmpty()): ?>
            <a class="btn btn-outline-secondary" href="/admin/rubrics/<?= $form['parent'] !== 0 ? '?id=' . $form['parent'] : '' ?>">Отмена</a>
<?php endif ?>
<?php endif ?>
          </div>
        </form>
      </section>
<?php endif ?>
    </div>
  </div>
</main>

<!-- Delete rubric: filled in from the data-es-rubric-* attributes of the button that opens it -->
<div class="modal fade" id="deleteRubricModal" tabindex="-1" aria-labelledby="deleteRubricTitle" aria-hidden="true" data-es-rubric-delete-modal>
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" action="/admin/rubrics/">
      <?= $csrf ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="" data-es-rubric-delete-id>
      <div class="modal-header">
        <h2 class="modal-title h4" id="deleteRubricTitle">Удалить раздел «<span data-es-rubric-delete-name></span>»?</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" data-es-rubric-delete-empty>В разделе нет ни предметов, ни подразделов. Удаление нельзя отменить.</p>
        <p class="mb-0" data-es-rubric-delete-busy hidden>У раздела есть подразделы. Сначала перенесите их в другой раздел (перетаскиванием в дереве) или удалите.</p>
        <div data-es-rubric-delete-items hidden>
          <p data-es-rubric-delete-summary></p>
          <div data-es-rubric-delete-choice>
            <div class="form-check mb-2">
              <input class="form-check-input" type="radio" name="items" id="delMove" value="move" checked>
              <label class="form-check-label" for="delMove">Перенести в другой раздел</label>
            </div>
            <select class="form-select mb-3 ms-4 w-auto mw-100" name="target" aria-label="Раздел для переноса" data-es-rubric-delete-target>
<?php foreach ($targets as $option): ?>
              <option value="<?= $option['id'] ?>"><?= $this->e($option['label']) ?></option>
<?php endforeach ?>
            </select>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="items" id="delAll" value="delete">
              <label class="form-check-label" for="delAll" data-es-rubric-delete-all>Удалить эти предметы</label>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="submit" class="btn btn-danger" data-es-rubric-delete-submit>Удалить раздел</button>
      </div>
    </form>
  </div>
</div>
