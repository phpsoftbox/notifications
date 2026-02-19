<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Contracts;

interface NotificationAuditStoreInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function store(
        ?int $recipientUserId,
        ?string $recipientTarget,
        string $channel,
        string $status,
        ?string $notificationType,
        ?string $title,
        ?string $body,
        array $payload = [],
        ?int $senderUserId = null,
        ?string $senderName = null,
    ): void;
}
