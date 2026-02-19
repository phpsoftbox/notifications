<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications;

enum NotificationChannelNames: string
{
    case EMAIL    = 'email';
    case TELEGRAM = 'telegram';
    case DATABASE = 'database';
    case SMS      = 'sms';
    case PUSH     = 'push';
    case PUSHR    = 'pushr';
}
