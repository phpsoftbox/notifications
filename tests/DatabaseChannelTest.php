<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Tests;

use PDO;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Contracts\DriverInterface;
use PhpSoftBox\Database\IsolationLevelEnum;
use PhpSoftBox\Database\QueryBuilder\QueryFactory;
use PhpSoftBox\Database\SchemaBuilder\SchemaBuilderInterface;
use PhpSoftBox\Notifications\Database\DatabaseChannel;
use PhpSoftBox\Notifications\Database\DatabaseNotificationInterface;
use PhpSoftBox\Notifications\Database\DatabaseNotificationRepository;
use PhpSoftBox\Notifications\Message\DatabaseMessage;
use PhpSoftBox\Notifications\NotifiableInterface;
use PhpSoftBox\Notifications\Notification;
use PhpSoftBox\Notifications\NotificationChannelNames;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(DatabaseChannel::class)]
#[CoversClass(DatabaseNotificationRepository::class)]
#[CoversClass(DatabaseMessage::class)]
#[CoversMethod(DatabaseChannel::class, 'send')]
#[CoversMethod(DatabaseNotificationRepository::class, 'store')]
final class DatabaseChannelTest extends TestCase
{
    /**
     * Проверяет, что database-канал сохраняет уведомление через репозиторий.
     */
    #[Test]
    public function testDatabaseChannelStoresNotification(): void
    {
        $connection = new class () implements ConnectionInterface {
            public ?string $lastSql  = null;
            public array $lastParams = [];

            public function pdo(): PDO
            {
                throw new RuntimeException('Not used');
            }

            public function fetchAll(string $sql, array $params = []): array
            {
                return [];
            }

            public function fetchOne(string $sql, array $params = []): ?array
            {
                return null;
            }

            public function execute(string $sql, array $params = []): int
            {
                $this->lastSql    = $sql;
                $this->lastParams = $params;

                return 1;
            }

            public function transaction(callable $fn, ?IsolationLevelEnum $isolationLevel = null): mixed
            {
                return $fn($this);
            }

            public function lastInsertId(?string $name = null): string
            {
                return '1';
            }

            public function prefix(): string
            {
                return '';
            }

            public function table(string $name): string
            {
                return $name;
            }

            public function quoteIdentifier(string $identifier): string
            {
                return $identifier;
            }

            public function quoteTable(string $table): string
            {
                return $this->table($table);
            }

            public function isReadOnly(): bool
            {
                return false;
            }

            public function schema(): SchemaBuilderInterface
            {
                throw new RuntimeException('Not used');
            }

            public function logger(): ?LoggerInterface
            {
                return null;
            }

            public function query(): QueryFactory
            {
                throw new RuntimeException('Not used');
            }

            public function driver(): DriverInterface
            {
                throw new RuntimeException('Not used');
            }
        };

        $repository = new DatabaseNotificationRepository($connection);

        $channel = new DatabaseChannel($repository);

        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel, ?string $driver = null): mixed
            {
                return 7;
            }
        };

        $notification = new class () extends Notification implements DatabaseNotificationInterface {
            public function via(NotifiableInterface $notifiable): array
            {
                return [NotificationChannelNames::DATABASE->value];
            }

            public function toDatabase(NotifiableInterface $notifiable): DatabaseMessage
            {
                return DatabaseMessage::create('Test')->body('Payload');
            }
        };

        $result = $channel->send($notifiable, $notification);

        $this->assertTrue($result->isSent());
        $this->assertNotNull($connection->lastSql);
        $this->assertSame(7, $connection->lastParams['user_id']);
        $this->assertSame('Test', $connection->lastParams['title']);
    }
}
