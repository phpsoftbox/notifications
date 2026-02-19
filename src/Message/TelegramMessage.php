<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Message;

final class TelegramMessage
{
    private string $text;
    /** @var array<string, mixed> */
    private array $options = [];
    private ?string $bot   = null;

    public static function create(string $text): self
    {
        $message = new self();

        $message->text($text);

        return $message;
    }

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function bot(string $name): self
    {
        $this->bot = $name;

        return $this;
    }

    public function botName(): ?string
    {
        return $this->bot;
    }

    public function textBody(): string
    {
        return $this->text;
    }

    /**
     * @return array<string, mixed>
     */
    public function optionsData(): array
    {
        return $this->options;
    }
}
