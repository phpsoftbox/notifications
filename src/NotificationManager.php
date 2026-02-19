<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications;

use PhpSoftBox\Notifications\Contracts\NotificationChannelInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class NotificationManager
{
    /**
     * @var array<string, NotificationChannelInterface>
     */
    private array $channels = [];

    /**
     * @param list<NotificationChannelInterface> $channels
     */
    public function __construct(
        array $channels = [],
        private readonly ?LoggerInterface $logger = null,
    ) {
        foreach ($channels as $channel) {
            $this->registerChannel($channel);
        }
    }

    public function registerChannel(NotificationChannelInterface $channel): void
    {
        $this->channels[$channel->name()] = $channel;
    }

    public function channel(string $name): ?NotificationChannelInterface
    {
        return $this->channels[$name] ?? null;
    }

    /**
     * @param list<string>|null $channels
     * @return array<string, NotificationSendResult>
     */
    public function send(
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
        ?array $channels = null,
    ): array {
        $channels = $channels ?? $notification->via($notifiable);
        $results  = [];

        foreach ($channels as $channelName) {
            if ($channelName === '') {
                continue;
            }

            if (!$notification->shouldSend($notifiable, $channelName)) {
                $results[$channelName] = NotificationSendResult::skipped($channelName, 'Skipped by notification.');
                continue;
            }

            $channel = $this->channels[$channelName] ?? null;
            if ($channel === null) {
                $results[$channelName] = NotificationSendResult::failed($channelName, 'Channel is not registered.');
                continue;
            }

            if (!$channel->isAvailable()) {
                $results[$channelName] = NotificationSendResult::skipped($channelName, 'Channel dependency is not available.');
                continue;
            }

            try {
                $results[$channelName] = $channel->send($notifiable, $notification);
            } catch (Throwable $exception) {
                $this->logger?->error('Notification send failed: ' . $exception->getMessage(), [
                    'channel'           => $channelName,
                    'exception_class'   => $exception::class,
                    'exception_message' => $exception->getMessage(),
                    'exception'         => $exception,
                ]);

                $results[$channelName] = NotificationSendResult::failed(
                    $channelName,
                    $exception->getMessage(),
                    $exception,
                );
            }
        }

        return $results;
    }
}
