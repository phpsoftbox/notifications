<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Message;

final class DatabaseMessage
{
    private ?string $title = null;
    private ?string $body  = null;
    /** @var array<string, mixed> */
    private array $data = [];

    public static function create(?string $title = null): self
    {
        $message = new self();

        if ($title !== null) {
            $message->title($title);
        }

        return $message;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function data(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function titleText(): ?string
    {
        return $this->title;
    }

    public function bodyText(): ?string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function dataPayload(): array
    {
        return $this->data;
    }
}
