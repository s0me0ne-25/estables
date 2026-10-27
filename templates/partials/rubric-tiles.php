<?php

/**
 * Rubrics as cards: the top level on the catalogue root, subrubrics on a
 * rubric page. The cover is the rubric's own, else the first photo of its
 * first item, else that of a subrubric; without any the mount stays empty.
 *
 * @var \Estables\Core\View          $this
 * @var \Estables\Catalog\RubricTree $tree
 * @var list<int>                    $ids
 * @var bool                         $admin
 * @var \Estables\Media\MediaStore    $media
 */
?>
      <div class="row row-cols-2 row-cols-md-3 row-cols-xxl-4 g-3 g-md-4 es-catalog mb-4">
<?php foreach ($ids as $id):
    $node = $tree->node($id);
    $sub = \count(array_filter($tree->children($id), static fn(int $c): bool => $admin || $tree->node($c)['visible']));
    $meta = [\Estables\Core\Text::plural($node['count'], 'предмет', 'предмета', 'предметов')];
    if ($sub > 0) {
        $meta[] = \Estables\Core\Text::plural($sub, 'подраздел', 'подраздела', 'подразделов');
    }
?>
        <div class="col"><article class="es-specimen">
<?php if (($cover = \Estables\Media\Image::unpack($node['cover'])) !== null): ?>
          <div class="es-mount ratio ratio-4x3"><img src="<?= $this->e($media->thumbUrl($cover)) ?>" alt="" loading="lazy"></div>
<?php else: ?>
          <div class="es-mount ratio ratio-4x3"><div class="es-mount-empty"><i class="bi bi-images" aria-hidden="true"></i></div></div>
<?php endif ?>
          <div class="es-label">
            <h3><a href="<?= $this->e($tree->url($id)) ?>"><?= $this->e($node['name']) ?></a></h3>
            <p class="es-meta"><?= $this->e(implode(' · ', $meta)) ?><?php if (!$node['visible']): ?> <span class="es-status es-status-draft">Скрыт</span><?php endif ?></p>
          </div>
        </article></div>
<?php endforeach ?>
      </div>
