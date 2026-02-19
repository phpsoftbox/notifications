<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Contracts;

use PhpSoftBox\Notifications\Audit\NotificationAuditActor;

interface NotificationAuditActorResolverInterface
{
    public function resolve(): NotificationAuditActor;
}
