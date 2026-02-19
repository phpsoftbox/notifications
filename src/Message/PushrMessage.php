<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Message;

final readonly class PushrMessage
{
    public function __construct(
        private string $event,
        private mixed $payload = null,
        private ?string $channel = null,
        private ?string $driver = null,
    ) {
    }

    public function eventName(): string
    {
        return $this->event;
    }

    public function payload(): mixed
    {
        return $this->payload;
    }

    public function channelName(): ?string
    {
        return $this->channel;
    }

    public function driverName(): ?string
    {
        return $this->driver;
    }
}
