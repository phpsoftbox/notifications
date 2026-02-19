<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Tests;

use PhpSoftBox\Notifications\Contracts\PushrPublisherInterface;
use PhpSoftBox\Notifications\Message\PushrMessage;
use PhpSoftBox\Notifications\NotifiableInterface;
use PhpSoftBox\Notifications\Notification;
use PhpSoftBox\Notifications\NotificationChannelNames;
use PhpSoftBox\Notifications\NotificationSendResult;
use PhpSoftBox\Notifications\Pushr\PushrChannel;
use PhpSoftBox\Notifications\Pushr\PushrNotificationInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PushrChannel::class)]
#[CoversMethod(PushrChannel::class, 'send')]
final class PushrChannelTest extends TestCase
{
    /**
     * Проверяет, что PushrChannel публикует событие в заданный канал.
     */
    #[Test]
    public function testPublish(): void
    {
        $publisher = new class () implements PushrPublisherInterface {
            public array $calls = [];

            public function publish(string $channel, string $event, mixed $data = null): void
            {
                $this->calls[] = [$channel, $event, $data];
            }
        };

        $channel = new PushrChannel($publisher);

        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel, ?string $driver = null): mixed
            {
                return 'admin.private.user.1';
            }
        };

        $notification = new class () extends Notification implements PushrNotificationInterface {
            public function via(NotifiableInterface $notifiable): array
            {
                return [NotificationChannelNames::PUSHR->value];
            }

            public function toPushr(NotifiableInterface $notifiable): PushrMessage
            {
                return new PushrMessage('notification.created', ['id' => 1]);
            }
        };

        $result = $channel->send($notifiable, $notification);

        $this->assertSame(NotificationSendResult::STATUS_SENT, $result->status());
        $this->assertCount(1, $publisher->calls);
        $this->assertSame('admin.private.user.1', $publisher->calls[0][0]);
        $this->assertSame('notification.created', $publisher->calls[0][1]);
    }

    /**
     * Проверяет, что PushrChannel пропускает отправку без канала.
     */
    #[Test]
    public function testSkipWhenNoChannel(): void
    {
        $publisher = new class () implements PushrPublisherInterface {
            public function publish(string $channel, string $event, mixed $data = null): void
            {
            }
        };

        $channel = new PushrChannel($publisher);

        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel, ?string $driver = null): mixed
            {
                return null;
            }
        };

        $notification = new class () extends Notification implements PushrNotificationInterface {
            public function via(NotifiableInterface $notifiable): array
            {
                return [NotificationChannelNames::PUSHR->value];
            }

            public function toPushr(NotifiableInterface $notifiable): PushrMessage
            {
                return new PushrMessage('notification.created', ['id' => 1]);
            }
        };

        $result = $channel->send($notifiable, $notification);

        $this->assertSame(NotificationSendResult::STATUS_SKIPPED, $result->status());
    }
}
