<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Pushr;

use PhpSoftBox\Broadcaster\Pushr\PushrPublisher;
use PhpSoftBox\Notifications\Contracts\NotificationChannelInterface;
use PhpSoftBox\Notifications\Contracts\PushrPublisherInterface;
use PhpSoftBox\Notifications\Message\PushrMessage;
use PhpSoftBox\Notifications\NotifiableInterface;
use PhpSoftBox\Notifications\NotificationChannelNames;
use PhpSoftBox\Notifications\NotificationInterface;
use PhpSoftBox\Notifications\NotificationSendResult;

use function array_filter;
use function array_map;
use function array_values;
use function class_exists;
use function is_array;
use function is_string;

final class PushrChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly PushrPublisherInterface $publisher,
    ) {
    }

    public function name(): string
    {
        return NotificationChannelNames::PUSHR->value;
    }

    public function isAvailable(): bool
    {
        return class_exists(PushrPublisher::class);
    }

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): NotificationSendResult
    {
        if (!$notification instanceof PushrNotificationInterface) {
            return NotificationSendResult::failed($this->name(), 'Notification does not implement PushrNotificationInterface.');
        }

        $message = $notification->toPushr($notifiable);

        $channels = $this->resolveChannels($notifiable, $message);
        if ($channels === []) {
            return NotificationSendResult::skipped($this->name(), 'No pushr channels.');
        }

        foreach ($channels as $channel) {
            $this->publisher->publish($channel, $message->eventName(), $message->payload());
        }

        return NotificationSendResult::sent($this->name());
    }

    /**
     * @return list<string>
     */
    private function resolveChannels(NotifiableInterface $notifiable, PushrMessage $message): array
    {
        $explicit = $message->channelName();
        if (is_string($explicit) && $explicit !== '') {
            return [$explicit];
        }

        $route = $notifiable->routeNotificationFor($this->name(), $message->driverName());

        if (is_string($route)) {
            return $route !== '' ? [$route] : [];
        }

        if (is_array($route)) {
            return array_values(
                array_filter(
                    array_map('strval', $route),
                    static fn (string $value): bool => $value !== '',
                ),
            );
        }

        return [];
    }
}
