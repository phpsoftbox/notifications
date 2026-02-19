<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Audit;

final readonly class NotificationAuditActor
{
    public function __construct(
        public ?int $userId = null,
        public ?string $name = null,
    ) {
    }
}
