<?php

/**
 * Item page: the photo stage with thumbnails, the description, the neighbours.
 *
 * @var \Estables\Core\View                           $this
 * @var \Estables\Catalog\RubricTree                  $tree
 * @var \Estables\Catalog\Item                        $item
 * @var int                                          $main    rubric of the breadcrumbs, 0 if none is visible
 * @var list<int>                                    $others  other rubrics of the item the visitor may see
 * @var list<array{name: string, value: string}>     $attributes
 * @var list<array{number: string, finish: string, years: string}> $variants
 * @var array{prev: ?array{catno: string, slug: string, name: string}, next: ?array{catno: string, slug: string, name: string}} $neighbours
 * @var list<array{id: int, catno: string, slug: string, name: string, summary: string, yearFrom: ?int, yearTo: ?int, published: bool,
 *     updatedAt: \DateTimeImmutable, variants: int, main: int, rubrics: list<int>, photos: int, cover: ?\Estables\Media\Image}> $series
 * @var bool                                         $public  guests see this item
 * @var bool                                         $admin
 * @var list<array{image: \Estables\Media\Image, caption: string}> $photos
 * @var \Estables\Media\MediaStore                  $media
 */
$paragraphs = static fn(string $text): array => array_values(array_filter(array_map('trim', preg_split('~\n\s*\n~', trim($text)) ?: [])));
$path = static fn(int $id): string => implode(' / ', array_map(static fn(int $r): string => $tree->node($r)['name'], [...$tree->ancestors($id), $id]));
$description = $paragraphs($item->description);
$pager = array_values(array_filter([$neighbours['prev'], $neighbours['next']]));
?>
<main id="main" class="container-xxl px-3 px-lg-4 py-4">

  <nav aria-label="Путь по разделам">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="/">Каталог</a></li>
<?php if ($main > 0): ?>
<?php foreach ([...$tree->ancestors($main), $main] as $id): ?>
      <li class="breadcrumb-item"><a href="<?= $this->e($tree->url($id)) ?>"><?= $this->e($tree->node($id)['name']) ?></a></li>
<?php endforeach ?>
<?php endif ?>
      <li class="breadcrumb-item active" aria-current="page"><?= $this->e($item->catno) ?></li>
    </ol>
  </nav>

<?php if (!$public): ?>
  <div class="alert alert-warning" role="status"><i class="bi bi-eye-slash me-1" aria-hidden="true"></i><?= $item->published ? 'Все разделы предмета скрыты: посетители его не видят.' : 'Черновик: посетители этот предмет не видят.' ?></div>
<?php endif ?>

  <div class="row g-4 g-xl-5">

    <!-- Photos -->
    <div class="col-lg-7">
<?php if ($photos === []): ?>
      <div class="es-stage mb-2">
        <div class="ratio ratio-4x3"><div class="es-mount-empty"><i class="bi bi-image" aria-hidden="true"></i></div></div>
      </div>
      <p class="es-meta">Фотографий пока нет.</p>
<?php else:
    // A caption describes its photo; without one the item name stands in.
    $alt = fn(array $photo, int $i): string => $photo['caption'] !== '' ? $photo['caption'] : $item->name . (\count($photos) > 1 ? ', фото ' . ($i + 1) : '');
    $download = fn(array $photo, int $i): string => $media->downloadUrl($photo['image'], $item->slug . '-' . ($i + 1) . '.' . $photo['image']->ext);
    $first = $photos[0];
?>
      <div class="es-stage mb-2">
        <a href="<?= $this->e($media->largeUrl($first['image'])) ?>" aria-label="Открыть фото во весь экран" data-es-gallery-stage data-original="<?= $this->e($download($first, 0)) ?>" data-original-info="<?= $this->e($first['image']->label()) ?>">
          <img src="<?= $this->e($media->largeUrl($first['image'])) ?>" alt="<?= $this->e($alt($first, 0)) ?>">
          <span class="es-zoom"><i class="bi bi-arrows-fullscreen me-1" aria-hidden="true"></i><?= $this->e(\Estables\Core\Text::plural(\count($photos), 'фото', 'фото', 'фото')) ?></span>
        </a>
      </div>
<?php if (\count($photos) > 1): ?>
      <!-- Each thumbnail carries its original file for "Скачать оригинал" -->
      <div class="es-thumbs" role="group" aria-label="Фотографии предмета" data-es-gallery-thumbs>
<?php foreach ($photos as $i => $photo): ?>
        <a href="<?= $this->e($media->largeUrl($photo['image'])) ?>"<?= $i === 0 ? ' class="active" aria-current="true"' : '' ?> data-original="<?= $this->e($download($photo, $i)) ?>" data-original-info="<?= $this->e($photo['image']->label()) ?>"><img src="<?= $this->e($media->thumbUrl($photo['image'])) ?>" alt="<?= $this->e($alt($photo, $i)) ?>"></a>
<?php endforeach ?>
      </div>
<?php endif ?>
      <div class="es-photo-bar mt-2">
        <a class="btn btn-sm btn-outline-secondary" href="<?= $this->e($download($first, 0)) ?>" data-es-gallery-download><i class="bi bi-download me-1" aria-hidden="true"></i>Скачать оригинал</a>
        <span class="es-meta" data-es-gallery-info><?= $this->e($first['image']->label()) ?></span>
      </div>
<?php endif ?>
    </div>

    <!-- Description -->
    <div class="col-lg-5">
      <div class="d-flex justify-content-between align-items-start gap-3">
        <span class="es-catno"><?= $this->e($item->catno) ?></span>
<?php if ($admin): ?>
        <a class="btn btn-sm btn-outline-secondary" href="/admin/items/<?= $item->id ?>/"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Редактировать</a>
<?php endif ?>
      </div>
      <h1 class="mt-1 mb-3"><?= $this->e($item->name) ?></h1>
<?php foreach ($paragraphs($item->summary) as $paragraph): ?>
      <p class="es-lead"><?= nl2br($this->e($paragraph), false) ?></p>
<?php endforeach ?>

<?php if ($item->years() !== '' || $attributes !== [] || $others !== []): ?>
      <dl class="es-attrs my-4">
<?php if ($item->years() !== ''): ?>
        <dt>Годы выпуска</dt><dd><?= $this->e($item->years()) ?></dd>
<?php endif ?>
<?php foreach ($attributes as $attribute): ?>
        <dt><?= $this->e($attribute['name']) ?></dt><dd><?= $this->e($attribute['value']) ?></dd>
<?php endforeach ?>
<?php if ($others !== []): ?>
        <dt>Также в разделах</dt><dd><?php foreach ($others as $i => $id): ?><?= $i > 0 ? '<br>' : '' ?><a href="<?= $this->e($tree->url($id)) ?>"><?= $this->e($path($id)) ?></a><?php endforeach ?></dd>
<?php endif ?>
      </dl>
<?php endif ?>

<?php if ($variants !== []): ?>
      <h2 class="h3 mb-2">Варианты</h2>
      <div class="table-responsive">
        <table class="table es-variants align-middle mb-0">
          <thead>
            <tr><th scope="col">Номер</th><th scope="col">Исполнение</th><th scope="col">Годы</th></tr>
          </thead>
          <tbody>
<?php foreach ($variants as $variant): ?>
            <tr><td class="es-catno"><?= $this->e($variant['number']) ?></td><td><?= $this->e($variant['finish']) ?></td><td class="text-nowrap"><?= $this->e($variant['years']) ?></td></tr>
<?php endforeach ?>
          </tbody>
        </table>
      </div>
<?php endif ?>
    </div>
  </div>

<?php if ($description !== [] || $pager !== []): ?>
  <!-- Long description -->
  <section class="row g-4 g-xl-5 mt-2"<?= $description !== [] ? ' aria-labelledby="aboutTitle"' : '' ?>>
    <div class="col-lg-7">
<?php if ($description !== []): ?>
      <h2 id="aboutTitle" class="mb-3">Описание</h2>
      <div class="es-prose">
<?php foreach ($description as $paragraph): ?>
        <p><?= nl2br($this->e($paragraph), false) ?></p>
<?php endforeach ?>
      </div>
<?php endif ?>
    </div>
<?php if ($pager !== []): ?>
    <div class="col-lg-5">
      <h2 class="h3 mb-2">Соседние модели</h2>
      <div class="es-pager border-top">
<?php foreach ($pager as $neighbour): ?>
        <a href="/item/<?= $this->e($neighbour['slug']) ?>/" class="border-bottom"><span><?= $this->e($neighbour['catno']) ?></span><strong><?= $this->e($neighbour['name']) ?></strong></a>
<?php endforeach ?>
      </div>
    </div>
<?php endif ?>
  </section>
<?php endif ?>

<?php if ($series !== []): ?>
  <!-- Related items -->
  <section class="mt-5" aria-labelledby="relatedTitle">
    <h2 id="relatedTitle" class="mb-4">Из той же серии</h2>
<?= $this->partial('partials/item-cards', ['rows' => $series, 'class' => 'row row-cols-2 row-cols-md-4 g-4 es-catalog', 'toggle' => false]) ?>
  </section>
<?php endif ?>
</main>
