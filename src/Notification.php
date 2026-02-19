<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications;

abstract class Notification implements NotificationInterface
{
    public function shouldSend(NotifiableInterface $notifiable, string $channel): bool
    {
        return true;
    }
}
