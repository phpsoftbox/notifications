<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Email;

use PhpSoftBox\Mailer\Message\EmailMessage;
use PhpSoftBox\Notifications\NotifiableInterface;

interface EmailNotificationInterface
{
    public function toEmail(NotifiableInterface $notifiable): EmailMessage;
}
