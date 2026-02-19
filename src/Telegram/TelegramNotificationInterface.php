<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Telegram;

use PhpSoftBox\Notifications\Message\TelegramMessage;
use PhpSoftBox\Notifications\NotifiableInterface;

interface TelegramNotificationInterface
{
    public function toTelegram(NotifiableInterface $notifiable): TelegramMessage;
}
