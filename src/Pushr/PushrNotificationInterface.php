<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Pushr;

use PhpSoftBox\Notifications\Message\PushrMessage;
use PhpSoftBox\Notifications\NotifiableInterface;

interface PushrNotificationInterface
{
    public function toPushr(NotifiableInterface $notifiable): PushrMessage;
}
