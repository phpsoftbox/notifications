<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Tests;

use PhpSoftBox\Notifications\Contracts\NotificationChannelInterface;
use PhpSoftBox\Notifications\NotifiableInterface;
use PhpSoftBox\Notifications\Notification;
use PhpSoftBox\Notifications\NotificationInterface;
use PhpSoftBox\Notifications\NotificationManager;
use PhpSoftBox\Notifications\NotificationSendResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

#[CoversClass(NotificationManager::class)]
#[CoversMethod(NotificationManager::class, 'send')]
#[CoversMethod(NotificationManager::class, 'registerChannel')]
#[CoversMethod(NotificationManager::class, 'channel')]
final class NotificationManagerTest extends TestCase
{
    /**
     * Проверяет, что менеджер отправляет в зарегистрированные каналы,
     * пропускает shouldSend=false и возвращает ошибку для отсутствующего канала.
     */
    #[Test]
    public function testSendHandlesRegisteredAndSkippedChannels(): void
    {
        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel, ?string $driver = null): mixed
            {
                return null;
            }
        };

        $notification = new class () extends Notification {
            public function via(NotifiableInterface $notifiable): array
            {
                return ['demo', 'skip', 'missing'];
            }

            public function shouldSend(NotifiableInterface $notifiable, string $channel): bool
            {
                return $channel !== 'skip';
            }
        };

        $manager = new NotificationManager([
            new class () implements NotificationChannelInterface {
                public function name(): string
                {
                    return 'demo';
                }

                public function isAvailable(): bool
                {
                    return true;
                }

                public function send(NotifiableInterface $notifiable, NotificationInterface $notification): NotificationSendResult
                {
                    return NotificationSendResult::sent('demo');
                }
            },
        ]);

        $results = $manager->send($notifiable, $notification);

        $this->assertSame(NotificationSendResult::STATUS_SENT, $results['demo']->status());
        $this->assertSame(NotificationSendResult::STATUS_SKIPPED, $results['skip']->status());
        $this->assertSame(NotificationSendResult::STATUS_FAILED, $results['missing']->status());
    }

    #[Test]
    public function testSendLogsExceptionMessageAndClass(): void
    {
        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel, ?string $driver = null): mixed
            {
                return null;
            }
        };

        $notification = new class () extends Notification {
            public function via(NotifiableInterface $notifiable): array
            {
                return ['demo'];
            }
        };

        $logger = new class () extends AbstractLogger {
            public ?string $message = null;

            /**
             * @var array<string, mixed>|null
             */
            public ?array $context = null;

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->message = (string) $message;
                $this->context = $context;
            }
        };

        $manager = new NotificationManager([
            new class () implements NotificationChannelInterface {
                public function name(): string
                {
                    return 'demo';
                }

                public function isAvailable(): bool
                {
                    return true;
                }

                public function send(NotifiableInterface $notifiable, NotificationInterface $notification): NotificationSendResult
                {
                    throw new RuntimeException('SMTP connection failed during AUTH LOGIN.');
                }
            },
        ], $logger);

        $results = $manager->send($notifiable, $notification);

        $this->assertSame(NotificationSendResult::STATUS_FAILED, $results['demo']->status());
        $this->assertSame('Notification send failed: SMTP connection failed during AUTH LOGIN.', $logger->message);
        $this->assertSame('demo', $logger->context['channel'] ?? null);
        $this->assertSame(RuntimeException::class, $logger->context['exception_class'] ?? null);
        $this->assertSame('SMTP connection failed during AUTH LOGIN.', $logger->context['exception_message'] ?? null);
        $this->assertInstanceOf(RuntimeException::class, $logger->context['exception'] ?? null);
    }
}
