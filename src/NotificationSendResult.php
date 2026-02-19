<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications;

use Throwable;

final class NotificationSendResult
{
    public const STATUS_SENT    = 'sent';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED  = 'failed';

    private function __construct(
        private readonly string $channel,
        private readonly string $status,
        private readonly ?string $message = null,
        private readonly ?Throwable $exception = null,
    ) {
    }

    public static function sent(string $channel, ?string $message = null): self
    {
        return new self($channel, self::STATUS_SENT, $message);
    }

    public static function skipped(string $channel, ?string $message = null): self
    {
        return new self($channel, self::STATUS_SKIPPED, $message);
    }

    public static function failed(string $channel, ?string $message = null, ?Throwable $exception = null): self
    {
        return new self($channel, self::STATUS_FAILED, $message, $exception);
    }

    public function channel(): string
    {
        return $this->channel;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function exception(): ?Throwable
    {
        return $this->exception;
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }
}
