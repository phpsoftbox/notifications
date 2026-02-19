<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Pushr;

use PhpSoftBox\Broadcaster\Pushr\PushrPublisher;
use PhpSoftBox\Notifications\Contracts\PushrPublisherInterface;

final readonly class PushrPublisherAdapter implements PushrPublisherInterface
{
    public function __construct(
        private PushrPublisher $publisher,
    ) {
    }

    public function publish(string $channel, string $event, mixed $data = null): void
    {
        $this->publisher->publish($channel, $event, $data);
    }
}
