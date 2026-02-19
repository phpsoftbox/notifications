<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications;

interface NotificationInterface
{
    /**
     * @return list<string>
     */
    public function via(NotifiableInterface $notifiable): array;

    public function shouldSend(NotifiableInterface $notifiable, string $channel): bool;
}
