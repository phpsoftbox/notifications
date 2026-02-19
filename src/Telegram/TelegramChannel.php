<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Telegram;

use PhpSoftBox\Notifications\Contracts\NotificationChannelInterface;
use PhpSoftBox\Notifications\NotifiableInterface;
use PhpSoftBox\Notifications\NotificationChannelNames;
use PhpSoftBox\Notifications\NotificationInterface;
use PhpSoftBox\Notifications\NotificationSendResult;
use PhpSoftBox\Telegram\Bot\TelegramBotRegistry;

use function class_exists;
use function is_array;

final readonly class TelegramChannel implements NotificationChannelInterface
{
    public function __construct(
        private TelegramBotRegistry
    $bots,
    ) {
    }

    public function name(): string
    {
        return NotificationChannelNames::TELEGRAM->value;
    }

    public function isAvailable(): bool
    {
        return class_exists(TelegramBotRegistry::class);
    }

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): NotificationSendResult
    {
        if (!$notification instanceof TelegramNotificationInterface) {
            return NotificationSendResult::failed($this->name(), 'Notification does not implement TelegramNotificationInterface.');
        }

        $message = $notification->toTelegram($notifiable);
        $botName = $message->botName() ?? $this->bots->defaultName();
        $client  = $this->bots->client($botName);

        if ($client === null) {
            return NotificationSendResult::failed($this->name(), 'Telegram bot not found: ' . $botName);
        }

        $route = $notifiable->routeNotificationFor($this->name(), $botName);
        if ($route === null || $route === '') {
            return NotificationSendResult::skipped($this->name(), 'Telegram chat id is not configured.');
        }

        $response = $client->sendMessage($route, $message->textBody(), $message->optionsData());

        $messageId = null;
        $payload   = $response->result();
        if (is_array($payload) && isset($payload['message_id'])) {
            $messageId = (string) $payload['message_id'];
        }

        return NotificationSendResult::sent($this->name(), $messageId);
    }
}
