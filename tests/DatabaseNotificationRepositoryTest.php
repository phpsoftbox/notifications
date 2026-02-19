<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Tests;

use PDO;
use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Database\Contracts\DriverInterface;
use PhpSoftBox\Database\IsolationLevelEnum;
use PhpSoftBox\Database\QueryBuilder\QueryFactory;
use PhpSoftBox\Database\SchemaBuilder\SchemaBuilderInterface;
use PhpSoftBox\Notifications\Database\DatabaseNotificationRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

#[CoversClass(DatabaseNotificationRepository::class)]
#[CoversMethod(DatabaseNotificationRepository::class, 'listForUser')]
#[CoversMethod(DatabaseNotificationRepository::class, 'countUnread')]
#[CoversMethod(DatabaseNotificationRepository::class, 'markReadForUser')]
final class DatabaseNotificationRepositoryTest extends TestCase
{
    /**
     * Проверяет, что репозиторий строит запросы для списка и подсчёта непрочитанных.
     */
    #[Test]
    public function testListAndCountQueries(): void
    {
        $connection = new class () implements ConnectionInterface {
            public ?string $lastSql         = null;
            public array $lastParams        = [];
            public ?string $lastExecuteSql  = null;
            public array $lastExecuteParams = [];

            public function pdo(): PDO
            {
                throw new RuntimeException('Not used');
            }

            public function fetchAll(string $sql, array $params = []): array
            {
                $this->lastSql    = $sql;
                $this->lastParams = $params;

                return [];
            }

            public function fetchOne(string $sql, array $params = []): ?array
            {
                $this->lastSql    = $sql;
                $this->lastParams = $params;

                return ['total' => 3];
            }

            public function execute(string $sql, array $params = []): int
            {
                $this->lastExecuteSql    = $sql;
                $this->lastExecuteParams = $params;

                return 0;
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

        $repository->listForUser(5, 10);
        $this->assertNotNull($connection->lastSql);
        $this->assertSame(5, $connection->lastParams['user_id']);

        $count = $repository->countUnread(5);
        $this->assertSame(3, $count);
        $this->assertSame(5, $connection->lastParams['user_id']);
    }

    /**
     * Проверяет, что markReadForUser формирует корректный UPDATE.
     */
    #[Test]
    public function testMarkReadForUserQuery(): void
    {
        $connection = new class () implements ConnectionInterface {
            public ?string $lastExecuteSql  = null;
            public array $lastExecuteParams = [];

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
                $this->lastExecuteSql    = $sql;
                $this->lastExecuteParams = $params;

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

        $repository->markReadForUser(10, 5);

        $this->assertNotNull($connection->lastExecuteSql);
        $this->assertSame(10, $connection->lastExecuteParams['id']);
        $this->assertSame(5, $connection->lastExecuteParams['user_id']);
    }
}
