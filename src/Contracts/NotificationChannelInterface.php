<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Contracts;

use PhpSoftBox\Notifications\NotifiableInterface;
use PhpSoftBox\Notifications\NotificationInterface;
use PhpSoftBox\Notifications\NotificationSendResult;

interface NotificationChannelInterface
{
    public function name(): string;

    public function isAvailable(): bool;

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): NotificationSendResult;
}
