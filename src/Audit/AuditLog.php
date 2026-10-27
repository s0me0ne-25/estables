<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Audit;

use Estables\Auth\User;
use Estables\Core\Logger;
use Estables\Db\Connection;

/**
 * Who changed what: one entry per action of an administrator, in the
 * database (the "Журнал" page) and as a line of var/log/estables.log.
 *
 * The actor is set once per request (the signed-in administrator and the
 * address); without one the entry is made by the command line.
 *
 * @phpstan-type Entry array{id: int, at: \DateTimeImmutable, userId: ?int, actor: string, ip: string,
 *     action: string, label: string, objectId: ?int, subject: string, details: string, link: ?string}
 * @phpstan-type Filter array{who: string, what: string}
 */
final class AuditLog
{
    /** Kinds of actions: name of the group in the filter, action prefixes. */
    public const GROUPS = [
        'login' => ['Вход и выход', ['login', 'logout']],
        'items' => ['Предметы и фото', ['item.', 'items.']],
        'rubrics' => ['Разделы', ['rubric.']],
        'users' => ['Администраторы', ['user.']],
    ];

    /** What an action is called in the journal. */
    public const ACTIONS = [
        'login' => 'Вход',
        'logout' => 'Выход',
        'item.create' => 'Добавлен предмет',
        'item.update' => 'Изменён предмет',
        'item.delete' => 'Удалён предмет',
        'item.photo' => 'Добавлено фото',
        'item.order' => 'Изменён порядок',
        'items.publish' => 'Опубликованы',
        'items.unpublish' => 'Сняты с публикации',
        'items.delete' => 'Удалены предметы',
        'items.add-rubric' => 'Добавлены в раздел',
        'items.remove-rubric' => 'Убраны из раздела',
        'items.order' => 'Изменён порядок',
        'rubric.create' => 'Добавлен раздел',
        'rubric.update' => 'Изменён раздел',
        'rubric.move' => 'Перемещён раздел',
        'rubric.delete' => 'Удалён раздел',
        'user.create' => 'Добавлен администратор',
        'user.password' => 'Сменён пароль',
        'user.block' => 'Заблокирован',
        'user.unblock' => 'Разблокирован',
        'user.sessions' => 'Завершены сеансы',
        'user.unlock' => 'Сброшены неудачные входы',
    ];

    private const SUBJECT_MAX = 500;
    private const DETAILS_MAX = 4000;

    private ?User $user = null;
    private string $ip = '';

    public function __construct(
        private readonly Connection $db,
        private readonly Logger $logger,
    ) {}

    /**
     * Entries from now on are made by this administrator from this address.
     */
    public function actAs(User $user, string $ip): void
    {
        $this->user = $user;
        $this->ip = $ip;
    }

    /**
     * @param string   $action   a key of ACTIONS
     * @param string   $subject  what was changed, readable without the object ("ЛФЗ-1042 Конь вздыбленный")
     * @param int|null $objectId the item, rubric or administrator, for a link while it exists
     * @param string   $details  what exactly changed
     */
    public function record(string $action, string $subject, ?int $objectId = null, string $details = ''): void
    {
        $subject = mb_substr(trim($subject), 0, self::SUBJECT_MAX);
        $details = mb_substr(trim($details), 0, self::DETAILS_MAX);
        $actor = $this->user === null ? 'console' : Logger::quote($this->user->login) . ' from ' . $this->ip;
        $this->logger->info("Audit: {$actor} {$action}" . ($objectId !== null ? " #{$objectId}" : '') . ' ' . Logger::quote($subject)
            . ($details !== '' ? ': ' . Logger::quote($details) : ''));
        try {
            $this->db->run(
                'INSERT INTO audit_log (created_at, user_id, actor, ip, action, object_id, subject, details)
                 VALUES (UTC_TIMESTAMP(3), :user, :actor, :ip, :action, :object, :subject, :details)',
                [
                    'user' => $this->user?->id,
                    'actor' => mb_substr($this->user->login ?? '', 0, 100),
                    'ip' => $this->ip,
                    'action' => $action,
                    'object' => $objectId,
                    'subject' => $subject,
                    'details' => $details,
                ],
            );
        } catch (\PDOException $e) {
            // The change itself is done; losing its entry must not turn it into an error page.
            $this->logger->error('Audit entry not saved', $e);
        }
    }

    /**
     * A page of entries, newest first.
     *
     * @param Filter $filter who: "" all, "cli" the command line, or an administrator id; what: a key of GROUPS or ""
     *
     * @return array{rows: list<Entry>, total: int}
     */
    public function page(array $filter, int $page, int $perPage): array
    {
        [$where, $params] = self::where($filter);
        $total = (int) $this->db->value("SELECT COUNT(*) FROM audit_log WHERE {$where}", $params);
        $rows = $this->db->rows(
            // alive: the object can still be opened (a deleted item has no page to link to)
            "SELECT a.*, CASE
                WHEN a.object_id IS NULL THEN 0
                WHEN a.action LIKE 'item.%' THEN EXISTS (SELECT 1 FROM items i WHERE i.id = a.object_id)
                WHEN a.action LIKE 'rubric.%' THEN EXISTS (SELECT 1 FROM rubrics r WHERE r.id = a.object_id)
                ELSE 1 END AS alive
             FROM audit_log a WHERE {$where} ORDER BY a.id DESC LIMIT " . max(1, $perPage) . ' OFFSET ' . max(0, ($page - 1) * $perPage),
            $params,
        );

        return ['rows' => array_map(self::entry(...), $rows), 'total' => $total];
    }

    /**
     * Everyone who has entries, for the filter: administrators by id, then
     * the command line if it has entries.
     *
     * @return array<string, string> filter value => name
     */
    public function actors(): array
    {
        $actors = [];
        foreach ($this->db->rows(
            'SELECT a.user_id, COALESCE(u.login, MAX(a.actor)) AS login FROM audit_log a LEFT JOIN users u ON u.id = a.user_id
             WHERE a.user_id IS NOT NULL GROUP BY a.user_id, u.login ORDER BY login',
        ) as $row) {
            $actors[(string) $row['user_id']] = (string) $row['login'];
        }
        if ((int) $this->db->value('SELECT COUNT(*) FROM audit_log WHERE user_id IS NULL') > 0) {
            $actors['cli'] = 'Консоль';
        }

        return $actors;
    }

    /**
     * Deletes entries older than the given number of days; returns how many.
     */
    public function purge(int $days): int
    {
        return $this->db->run('DELETE FROM audit_log WHERE created_at < UTC_TIMESTAMP() - INTERVAL :days DAY', ['days' => max(1, $days)])->rowCount();
    }

    /**
     * Where an entry's object can be opened, while it exists.
     */
    public static function link(string $action, ?int $objectId): ?string
    {
        if ($objectId === null || str_ends_with($action, '.delete')) {
            return null;
        }

        return match (strstr($action, '.', true)) {
            'item' => "/admin/items/{$objectId}/",
            'rubric' => "/admin/rubrics/?id={$objectId}",
            'user' => '/admin/users/',
            default => null,
        };
    }

    /**
     * @param Filter $filter
     *
     * @return array{0: string, 1: array<string, int|string>}
     */
    private static function where(array $filter): array
    {
        $conditions = ['1 = 1'];
        $params = [];
        if ($filter['who'] === 'cli') {
            $conditions[] = 'user_id IS NULL';
        } elseif ($filter['who'] !== '') {
            $conditions[] = 'user_id = :who';
            $params['who'] = (int) $filter['who'];
        }
        if (isset(self::GROUPS[$filter['what']])) {
            $or = [];
            foreach (self::GROUPS[$filter['what']][1] as $i => $prefix) {
                if (str_ends_with($prefix, '.')) {
                    $or[] = "action LIKE :what{$i}";
                    $params["what{$i}"] = $prefix . '%';
                } else {
                    $or[] = "action = :what{$i}";
                    $params["what{$i}"] = $prefix;
                }
            }
            $conditions[] = '(' . implode(' OR ', $or) . ')';
        }

        return [implode(' AND ', $conditions), $params];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return Entry
     */
    private static function entry(array $row): array
    {
        $action = (string) $row['action'];
        $objectId = $row['object_id'] === null ? null : (int) $row['object_id'];

        return [
            'id' => (int) $row['id'],
            'at' => new \DateTimeImmutable((string) $row['created_at'], new \DateTimeZone('UTC')),
            'userId' => $row['user_id'] === null ? null : (int) $row['user_id'],
            'actor' => (string) $row['actor'],
            'ip' => (string) $row['ip'],
            'action' => $action,
            'label' => self::ACTIONS[$action] ?? $action,
            'objectId' => $objectId,
            'subject' => (string) $row['subject'],
            'details' => (string) $row['details'],
            'link' => (int) ($row['alive'] ?? 1) === 1 ? self::link($action, $objectId) : null,
        ];
    }
}
