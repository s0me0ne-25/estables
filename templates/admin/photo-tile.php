<?php

/**
 * One photo in the item editor. The first one is the cover.
 *
 * @var \Estables\Core\View        $this
 * @var \Estables\Media\Image      $image
 * @var string                     $caption
 * @var int                        $index   position from 0
 * @var \Estables\Media\MediaStore $media
 */
$n = $index + 1;
$id = 'ph-' . substr($image->sha, 0, 12);
?>
          <div class="es-photo<?= $index === 0 ? ' is-cover' : '' ?>">
            <img src="<?= $this->e($media->thumbUrl($image)) ?>" alt="<?= $this->e($caption) ?>" loading="lazy">
<?php if ($index === 0): ?>
            <span class="es-photo-cover" data-es-photo-cover-badge>Обложка</span>
<?php endif ?>
            <input type="hidden" name="photo[]" value="<?= $image->sha ?>">
            <label class="visually-hidden" for="<?= $id ?>">Подпись к фото <?= $n ?></label>
            <input class="form-control form-control-sm" type="text" id="<?= $id ?>" name="photo_caption[]" value="<?= $this->e($caption) ?>" placeholder="Подпись" maxlength="500">
            <div class="es-photo-tools">
              <button class="es-drag" data-es-sortable-handle type="button" title="Перетащить"><i class="bi bi-grip-horizontal" aria-hidden="true"></i><span class="visually-hidden">Переместить фото <?= $n ?></span></button>
              <span>
              <button class="btn btn-sm es-btn-quiet" type="button" title="Сделать обложкой" data-es-photo-cover-set<?= $index === 0 ? ' hidden' : '' ?>><i class="bi bi-star" aria-hidden="true"></i><span class="visually-hidden">Сделать обложкой</span></button>
              <button class="btn btn-sm es-btn-quiet es-btn-quiet-danger" type="button" title="Удалить фото" data-es-photo-remove><i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">Удалить фото <?= $n ?></span></button>
              </span>
            </div>
          </div>
