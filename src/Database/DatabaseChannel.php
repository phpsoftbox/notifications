<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Database;

use PhpSoftBox\Database\Contracts\ConnectionInterface;
use PhpSoftBox\Notifications\Contracts\NotificationChannelInterface;
use PhpSoftBox\Notifications\NotifiableInterface;
use PhpSoftBox\Notifications\NotificationChannelNames;
use PhpSoftBox\Notifications\NotificationInterface;
use PhpSoftBox\Notifications\NotificationSendResult;

use function interface_exists;
use function is_int;
use function is_string;

final readonly class DatabaseChannel implements NotificationChannelInterface
{
    public function __construct(
        private DatabaseNotificationRepository $repository,
    ) {
    }

    public function name(): string
    {
        return NotificationChannelNames::DATABASE->value;
    }

    public function isAvailable(): bool
    {
        return interface_exists(ConnectionInterface::class);
    }

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): NotificationSendResult
    {
        if (!$notification instanceof DatabaseNotificationInterface) {
            return NotificationSendResult::failed($this->name(), 'Notification does not implement DatabaseNotificationInterface.');
        }

        $userId = $notifiable->routeNotificationFor($this->name());
        if (!is_int($userId) && (!is_string($userId) || $userId === '')) {
            return NotificationSendResult::skipped($this->name(), 'User id is not configured.');
        }

        $userId = (int) $userId;
        if ($userId <= 0) {
            return NotificationSendResult::skipped($this->name(), 'User id is not configured.');
        }

        $message = $notification->toDatabase($notifiable);
        $this->repository->store($userId, $message, $notification::class);

        return NotificationSendResult::sent($this->name());
    }
}
