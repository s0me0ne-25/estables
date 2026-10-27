<?php

/**
 * Admin journal: who changed what, newest first.
 *
 * @var \Estables\Core\View       $this
 * @var array{who: string, what: string} $filter
 * @var array<string, string>     $actors filter value => name
 * @var array<string, string>     $groups filter value => name
 * @var list<array{id: int, at: \DateTimeImmutable, userId: ?int, actor: string, ip: string, action: string, label: string,
 *     objectId: ?int, subject: string, details: string, link: ?string}> $rows
 * @var \Estables\Core\Pager      $pager
 * @var int                       $keepDays
 */
use Estables\Core\Text;

$query = array_filter($filter, static fn(string $v): bool => $v !== '');
$pageUrl = fn(int $page): string => '/admin/audit/?' . http_build_query(['page' => $page > 1 ? $page : null] + $query);
[$first, $last] = $pager->range();
?>
<main id="main" class="container-xxl px-3 px-lg-4 py-4">

  <div class="mb-4">
    <h1 class="mb-1">Журнал</h1>
    <p class="text-body-secondary mb-0">Кто и что менял в каталоге. Записи старше <?= $this->e(Text::plural($keepDays, 'дня', 'дней', 'дней')) ?> удаляет <code>bin/estables gc</code>.</p>
  </div>

  <!-- Filters -->
  <form class="row g-2 align-items-end mb-3" role="search" aria-label="Фильтр журнала" method="get" action="/admin/audit/" data-es-autosubmit>
    <div class="col-12 col-sm-6 col-md-4 col-xl-3">
      <label class="form-label small" for="f-who">Кто</label>
      <select class="form-select" id="f-who" name="who">
        <option value="">Все</option>
<?php foreach ($actors as $value => $name): ?>
        <option value="<?= $this->e($value) ?>"<?= (string) $value === $filter['who'] ? ' selected' : '' ?>><?= $this->e($name) ?></option>
<?php endforeach ?>
      </select>
    </div>
    <div class="col-12 col-sm-6 col-md-4 col-xl-3">
      <label class="form-label small" for="f-what">Что</label>
      <select class="form-select" id="f-what" name="what">
        <option value="">Все действия</option>
<?php foreach ($groups as $value => $name): ?>
        <option value="<?= $this->e($value) ?>"<?= $value === $filter['what'] ? ' selected' : '' ?>><?= $this->e($name) ?></option>
<?php endforeach ?>
      </select>
    </div>
    <button class="visually-hidden" type="submit">Показать</button>
  </form>

  <div class="table-responsive border-top">
    <table class="table es-admin-table es-audit">
      <thead>
        <tr>
          <th scope="col">Когда</th>
          <th scope="col">Кто</th>
          <th scope="col">Действие</th>
          <th scope="col">Что</th>
          <th scope="col">Подробности</th>
        </tr>
      </thead>
      <tbody>
<?php if ($rows === []): ?>
        <tr><td colspan="5" class="text-body-secondary py-4"><?= $query !== [] ? 'Ничего не нашлось. Измените условия фильтра.' : 'Записей пока нет.' ?></td></tr>
<?php endif ?>
<?php foreach ($rows as $row): ?>
        <tr>
          <td class="text-nowrap small text-body-secondary"><time datetime="<?= $row['at']->format('Y-m-d\TH:i:s\Z') ?>"><?= $this->e($this->dateTime($row['at'], 'd.m.Y, H:i')) ?></time></td>
          <td class="text-nowrap"><?php if ($row['userId'] === null && $row['actor'] === ''): ?><span class="text-body-secondary">Консоль</span><?php else: ?><?= $this->e($row['actor']) ?><span class="es-path"><?= $this->e($row['ip']) ?></span><?php endif ?></td>
          <td><?= $this->e($row['label']) ?></td>
          <td class="es-col-title"><?php if ($row['link'] !== null): ?><a class="es-title" href="<?= $this->e($row['link']) ?>"><?= $this->e($row['subject']) ?></a><?php else: ?><?= $this->e($row['subject']) ?><?php endif ?></td>
          <td class="small"><?= $this->e($row['details']) ?></td>
        </tr>
<?php endforeach ?>
      </tbody>
    </table>
  </div>

  <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mt-4">
    <span class="text-body-secondary small"><?= $pager->total > 0 ? "Показаны {$first}–{$last} из " . $this->e(Text::number($pager->total)) : 'Нет записей' ?></span>
<?= $this->partial('partials/pagination', ['pager' => $pager, 'pageUrl' => $pageUrl, 'label' => 'Страницы журнала']) ?>
  </div>
</main>
