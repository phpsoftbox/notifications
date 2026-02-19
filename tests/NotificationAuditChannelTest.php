<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Tests;

use PhpSoftBox\Mailer\Message\EmailMessage;
use PhpSoftBox\Notifications\Audit\NotificationAuditActor;
use PhpSoftBox\Notifications\Audit\NotificationAuditChannel;
use PhpSoftBox\Notifications\Contracts\NotificationAuditActorResolverInterface;
use PhpSoftBox\Notifications\Contracts\NotificationAuditStoreInterface;
use PhpSoftBox\Notifications\Contracts\NotificationAuditToggleInterface;
use PhpSoftBox\Notifications\Contracts\NotificationChannelInterface;
use PhpSoftBox\Notifications\Email\EmailNotificationInterface;
use PhpSoftBox\Notifications\NotifiableInterface;
use PhpSoftBox\Notifications\Notification;
use PhpSoftBox\Notifications\NotificationInterface;
use PhpSoftBox\Notifications\NotificationSendResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(NotificationAuditChannel::class)]
#[CoversMethod(NotificationAuditChannel::class, 'send')]
final class NotificationAuditChannelTest extends TestCase
{
    /**
     * Проверяет, что при успешной отправке канал пишет запись в историю.
     */
    #[Test]
    public function testSendStoresAuditOnSuccess(): void
    {
        $store = new class () implements NotificationAuditStoreInterface {
            /**
             * @var array<int, array<string, mixed>>
             */
            private array $stored = [];

            public function store(
                ?int $recipientUserId,
                ?string $recipientTarget,
                string $channel,
                string $status,
                ?string $notificationType,
                ?string $title,
                ?string $body,
                array $payload = [],
                ?int $senderUserId = null,
                ?string $senderName = null,
            ): void {
                $this->stored[] = [
                    'recipientUserId' => $recipientUserId,
                    'recipientTarget' => $recipientTarget,
                    'channel'         => $channel,
                    'status'          => $status,
                    'title'           => $title,
                ];
            }

            /**
             * @return array<int, array<string, mixed>>
             */
            public function all(): array
            {
                return $this->stored;
            }
        };

        $actorResolver = new class () implements NotificationAuditActorResolverInterface {
            public function resolve(): NotificationAuditActor
            {
                return new NotificationAuditActor(userId: 12, name: 'Admin');
            }
        };

        $toggle = new class () implements NotificationAuditToggleInterface {
            public function isEnabled(): bool
            {
                return true;
            }
        };

        $inner = new class () implements NotificationChannelInterface {
            public function name(): string
            {
                return 'email';
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function send(NotifiableInterface $notifiable, NotificationInterface $notification): NotificationSendResult
            {
                return NotificationSendResult::sent('email');
            }
        };

        $channel = new NotificationAuditChannel($inner, $store, $actorResolver, $toggle);

        $notification = new class () extends Notification implements EmailNotificationInterface {
            public function via(NotifiableInterface $notifiable): array
            {
                return ['email'];
            }

            public function toEmail(NotifiableInterface $notifiable): EmailMessage
            {
                return EmailMessage::create('Hello')->text('Body');
            }
        };

        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel, ?string $driver = null): mixed
            {
                if ($channel === 'database') {
                    return 55;
                }

                if ($channel === 'email') {
                    return 'user@example.com';
                }

                return null;
            }
        };

        $result = $channel->send($notifiable, $notification);
        $stored = $store->all();

        $this->assertTrue($result->isSent());
        $this->assertCount(1, $stored);
        $this->assertSame(55, $stored[0]['recipientUserId']);
        $this->assertSame('user@example.com', $stored[0]['recipientTarget']);
        $this->assertSame('email', $stored[0]['channel']);
        $this->assertSame('sent', $stored[0]['status']);
        $this->assertSame('Hello', $stored[0]['title']);
    }

    /**
     * Проверяет, что при ошибке отправки запись со статусом failed сохраняется и исключение пробрасывается.
     */
    #[Test]
    public function testSendStoresFailedAndRethrows(): void
    {
        $store = new class () implements NotificationAuditStoreInterface {
            /**
             * @var array<int, array<string, mixed>>
             */
            private array $stored = [];

            public function store(
                ?int $recipientUserId,
                ?string $recipientTarget,
                string $channel,
                string $status,
                ?string $notificationType,
                ?string $title,
                ?string $body,
                array $payload = [],
                ?int $senderUserId = null,
                ?string $senderName = null,
            ): void {
                $this->stored[] = ['status' => $status];
            }

            /**
             * @return array<int, array<string, mixed>>
             */
            public function all(): array
            {
                return $this->stored;
            }
        };

        $actorResolver = new class () implements NotificationAuditActorResolverInterface {
            public function resolve(): NotificationAuditActor
            {
                return new NotificationAuditActor();
            }
        };

        $toggle = new class () implements NotificationAuditToggleInterface {
            public function isEnabled(): bool
            {
                return true;
            }
        };

        $inner = new class () implements NotificationChannelInterface {
            public function name(): string
            {
                return 'email';
            }

            public function isAvailable(): bool
            {
                return true;
            }

            public function send(NotifiableInterface $notifiable, NotificationInterface $notification): NotificationSendResult
            {
                throw new RuntimeException('fail');
            }
        };

        $channel = new NotificationAuditChannel($inner, $store, $actorResolver, $toggle);

        $notification = new class () extends Notification {
            public function via(NotifiableInterface $notifiable): array
            {
                return ['email'];
            }
        };

        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel, ?string $driver = null): mixed
            {
                return null;
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fail');

        try {
            $channel->send($notifiable, $notification);
        } finally {
            $stored = $store->all();
            $this->assertCount(1, $stored);
            $this->assertSame('failed', $stored[0]['status']);
        }
    }
}
