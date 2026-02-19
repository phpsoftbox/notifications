<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications;

interface NotifiableInterface
{
    /**
     * @param string|null $driver Имя драйвера/бота (например, имя Telegram-бота)
     */
    public function routeNotificationFor(string $channel, ?string $driver = null): mixed;
}
