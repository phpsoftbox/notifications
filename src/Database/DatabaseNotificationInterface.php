<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Database;

use PhpSoftBox\Notifications\Message\DatabaseMessage;
use PhpSoftBox\Notifications\NotifiableInterface;

interface DatabaseNotificationInterface
{
    public function toDatabase(NotifiableInterface $notifiable): DatabaseMessage;
}
