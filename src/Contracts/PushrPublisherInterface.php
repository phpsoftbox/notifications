<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Contracts;

interface PushrPublisherInterface
{
    public function publish(string $channel, string $event, mixed $data = null): void;
}
