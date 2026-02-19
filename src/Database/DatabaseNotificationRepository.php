<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Database;

use DateTimeInterface;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Notifications\Message\DatabaseMessage;

use function implode;
use function json_encode;
use function sprintf;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class DatabaseNotificationRepository
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $table = 'notifications',
    ) {
    }

    public function store(int $userId, DatabaseMessage $message, ?string $type = null): void
    {
        $payload = $message->dataPayload();
        $data    = $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        $sql = sprintf(
            'INSERT INTO %s (user_id, type, title, body, data, read_datetime, created_datetime, updated_datetime) '
            . 'VALUES (:user_id, :type, :title, :body, :data, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            $this->connection->table($this->table),
        );

        $this->connection->execute($sql, [
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $message->titleText(),
            'body'    => $message->bodyText(),
            'data'    => $data,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId, int $limit = 5): array
    {
        $limit = $limit > 0 ? $limit : 5;

        $sql = sprintf(
            'SELECT id, user_id, type, title, body, data, read_datetime, created_datetime FROM %s WHERE user_id = :user_id ORDER BY created_datetime DESC LIMIT %d',
            $this->connection->table($this->table),
            $limit,
        );

        return $this->connection->fetchAll($sql, ['user_id' => $userId]);
    }

    public function countUnread(int $userId): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) as total FROM %s WHERE user_id = :user_id AND read_datetime IS NULL',
            $this->connection->table($this->table),
        );

        $row = $this->connection->fetchOne($sql, ['user_id' => $userId]);

        return (int) ($row['total'] ?? 0);
    }

    public function markRead(int $id): int
    {
        $sql = sprintf(
            'UPDATE %s SET read_datetime = CURRENT_TIMESTAMP, updated_datetime = CURRENT_TIMESTAMP WHERE id = :id',
            $this->connection->table($this->table),
        );

        return $this->connection->execute($sql, ['id' => $id]);
    }

    public function markReadForUser(int $id, int $userId): int
    {
        $sql = sprintf(
            'UPDATE %s SET read_datetime = CURRENT_TIMESTAMP, updated_datetime = CURRENT_TIMESTAMP WHERE id = :id AND user_id = :user_id',
            $this->connection->table($this->table),
        );

        return $this->connection->execute($sql, ['id' => $id, 'user_id' => $userId]);
    }

    public function markAllRead(int $userId): int
    {
        $sql = sprintf(
            'UPDATE %s SET read_datetime = CURRENT_TIMESTAMP, updated_datetime = CURRENT_TIMESTAMP WHERE user_id = :user_id AND read_datetime IS NULL',
            $this->connection->table($this->table),
        );

        return $this->connection->execute($sql, ['user_id' => $userId]);
    }

    public function prune(?DateTimeInterface $before = null, bool $onlyRead = false): int
    {
        $conditions = [];
        $params     = [];

        if ($onlyRead) {
            $conditions[] = 'read_datetime IS NOT NULL';
        }

        if ($before !== null) {
            $conditions[]     = 'created_datetime < :before';
            $params['before'] = $before->format('Y-m-d H:i:s');
        }

        $where = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

        $sql = sprintf('DELETE FROM %s%s', $this->connection->table($this->table), $where);

        return $this->connection->execute($sql, $params);
    }
}
