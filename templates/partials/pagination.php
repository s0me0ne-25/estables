<?php

/**
 * Page links of a list (Bootstrap pagination).
 *
 * @var \Estables\Core\View      $this
 * @var \Estables\Core\Pager     $pager
 * @var callable(int): string    $pageUrl address of a page
 * @var string                   $label   accessible name of the navigation
 * @var string                   $class   classes of the <ul>
 */
$class ??= 'mb-0';
if ($pager->pages < 2) {
    return;
}
?>
<nav aria-label="<?= $this->e($label) ?>"<?= isset($navClass) ? ' class="' . $this->e($navClass) . '"' : '' ?>>
  <ul class="pagination <?= $this->e($class) ?>">
<?php if ($pager->page > 1): ?>
    <li class="page-item"><a class="page-link" href="<?= $this->e($pageUrl($pager->page - 1)) ?>" rel="prev"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Назад</span></a></li>
<?php else: ?>
    <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-left" aria-hidden="true"></i><span class="visually-hidden">Назад</span></span></li>
<?php endif ?>
<?php foreach ($pager->links() as $page): ?>
<?php if ($page === null): ?>
    <li class="page-item disabled"><span class="page-link">…</span></li>
<?php elseif ($page === $pager->page): ?>
    <li class="page-item active" aria-current="page"><span class="page-link"><?= $page ?></span></li>
<?php else: ?>
    <li class="page-item"><a class="page-link" href="<?= $this->e($pageUrl($page)) ?>"><?= $page ?></a></li>
<?php endif ?>
<?php endforeach ?>
<?php if ($pager->page < $pager->pages): ?>
    <li class="page-item"><a class="page-link" href="<?= $this->e($pageUrl($pager->page + 1)) ?>" rel="next"><i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden">Вперёд</span></a></li>
<?php else: ?>
    <li class="page-item disabled"><span class="page-link"><i class="bi bi-chevron-right" aria-hidden="true"></i><span class="visually-hidden">Вперёд</span></span></li>
<?php endif ?>
  </ul>
</nav>
