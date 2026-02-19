<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Contracts;

interface NotificationAuditToggleInterface
{
    public function isEnabled(): bool;
}
