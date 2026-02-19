<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Audit;

use JsonException;
use PhpSoftBox\Mailer\Message\EmailMessage;
use PhpSoftBox\Notifications\Contracts\NotificationAuditActorResolverInterface;
use PhpSoftBox\Notifications\Contracts\NotificationAuditStoreInterface;
use PhpSoftBox\Notifications\Contracts\NotificationAuditToggleInterface;
use PhpSoftBox\Notifications\Contracts\NotificationChannelInterface;
use PhpSoftBox\Notifications\Database\DatabaseNotificationInterface;
use PhpSoftBox\Notifications\Email\EmailNotificationInterface;
use PhpSoftBox\Notifications\NotifiableInterface;
use PhpSoftBox\Notifications\NotificationChannelNames;
use PhpSoftBox\Notifications\NotificationInterface;
use PhpSoftBox\Notifications\NotificationSendResult;
use PhpSoftBox\Notifications\Telegram\TelegramNotificationInterface;
use Throwable;

use function get_class;
use function is_int;
use function is_numeric;
use function is_scalar;
use function json_encode;
use function trim;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class NotificationAuditChannel implements NotificationChannelInterface
{
    public function __construct(
        private NotificationChannelInterface $inner,
        private NotificationAuditStoreInterface $store,
        private NotificationAuditActorResolverInterface $actorResolver,
        private NotificationAuditToggleInterface $toggle,
    ) {
    }

    public function name(): string
    {
        return $this->inner->name();
    }

    public function isAvailable(): bool
    {
        return $this->inner->isAvailable();
    }

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): NotificationSendResult
    {
        $channelName = $this->name();
        $snapshot    = $this->extractSnapshot($channelName, $notifiable, $notification);

        try {
            $result = $this->inner->send($notifiable, $notification);
            $this->record($snapshot, $notification, $result, null);

            return $result;
        } catch (Throwable $exception) {
            $this->record(
                $snapshot,
                $notification,
                NotificationSendResult::failed($channelName, $exception->getMessage(), $exception),
                $exception,
            );

            throw $exception;
        }
    }

    /**
     * @return array{recipient_user_id: ?int, recipient_target: ?string, title: ?string, body: ?string, payload: array<string, mixed>}
     */
    private function extractSnapshot(string $channel, NotifiableInterface $notifiable, NotificationInterface $notification): array
    {
        $recipientUserId = $this->resolveRecipientUserId($notifiable);
        $recipientTarget = $this->resolveRecipientTarget($channel, $notifiable, $notification);

        return match ($channel) {
            NotificationChannelNames::DATABASE->value => $this->extractDatabaseSnapshot($notifiable, $notification, $recipientUserId, $recipientTarget),
            NotificationChannelNames::EMAIL->value    => $this->extractEmailSnapshot($notifiable, $notification, $recipientUserId, $recipientTarget),
            NotificationChannelNames::TELEGRAM->value => $this->extractTelegramSnapshot($notifiable, $notification, $recipientUserId, $recipientTarget),
            default                                   => [
                'recipient_user_id' => $recipientUserId,
                'recipient_target'  => $recipientTarget,
                'title'             => null,
                'body'              => null,
                'payload'           => [],
            ],
        };
    }

    /**
     * @return array{recipient_user_id: ?int, recipient_target: ?string, title: ?string, body: ?string, payload: array<string, mixed>}
     */
    private function extractDatabaseSnapshot(
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
        ?int $recipientUserId,
        ?string $recipientTarget,
    ): array {
        if (!$notification instanceof DatabaseNotificationInterface) {
            return [
                'recipient_user_id' => $recipientUserId,
                'recipient_target'  => $recipientTarget,
                'title'             => null,
                'body'              => null,
                'payload'           => [],
            ];
        }

        try {
            $message = $notification->toDatabase($notifiable);

            return [
                'recipient_user_id' => $recipientUserId,
                'recipient_target'  => $recipientTarget,
                'title'             => $message->titleText(),
                'body'              => $message->bodyText(),
                'payload'           => $message->dataPayload(),
            ];
        } catch (Throwable) {
            return [
                'recipient_user_id' => $recipientUserId,
                'recipient_target'  => $recipientTarget,
                'title'             => null,
                'body'              => null,
                'payload'           => [],
            ];
        }
    }

    /**
     * @return array{recipient_user_id: ?int, recipient_target: ?string, title: ?string, body: ?string, payload: array<string, mixed>}
     */
    private function extractEmailSnapshot(
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
        ?int $recipientUserId,
        ?string $recipientTarget,
    ): array {
        if (!$notification instanceof EmailNotificationInterface) {
            return [
                'recipient_user_id' => $recipientUserId,
                'recipient_target'  => $recipientTarget,
                'title'             => null,
                'body'              => null,
                'payload'           => [],
            ];
        }

        try {
            $message = $notification->toEmail($notifiable);

            return [
                'recipient_user_id' => $recipientUserId,
                'recipient_target'  => $recipientTarget,
                'title'             => $message->subjectText(),
                'body'              => $this->extractEmailBody($message),
                'payload'           => [
                    'to'       => $message->toAddresses(),
                    'cc'       => $message->ccAddresses(),
                    'bcc'      => $message->bccAddresses(),
                    'from'     => $message->fromAddress(),
                    'reply_to' => $message->replyToAddress(),
                    'template' => $message->templateName(),
                ],
            ];
        } catch (Throwable) {
            return [
                'recipient_user_id' => $recipientUserId,
                'recipient_target'  => $recipientTarget,
                'title'             => null,
                'body'              => null,
                'payload'           => [],
            ];
        }
    }

    /**
     * @return array{recipient_user_id: ?int, recipient_target: ?string, title: ?string, body: ?string, payload: array<string, mixed>}
     */
    private function extractTelegramSnapshot(
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
        ?int $recipientUserId,
        ?string $recipientTarget,
    ): array {
        if (!$notification instanceof TelegramNotificationInterface) {
            return [
                'recipient_user_id' => $recipientUserId,
                'recipient_target'  => $recipientTarget,
                'title'             => null,
                'body'              => null,
                'payload'           => [],
            ];
        }

        try {
            $message = $notification->toTelegram($notifiable);

            return [
                'recipient_user_id' => $recipientUserId,
                'recipient_target'  => $recipientTarget,
                'title'             => null,
                'body'              => $message->textBody(),
                'payload'           => [
                    'bot'     => $message->botName(),
                    'options' => $message->optionsData(),
                ],
            ];
        } catch (Throwable) {
            return [
                'recipient_user_id' => $recipientUserId,
                'recipient_target'  => $recipientTarget,
                'title'             => null,
                'body'              => null,
                'payload'           => [],
            ];
        }
    }

    /**
     * @param array{recipient_user_id: ?int, recipient_target: ?string, title: ?string, body: ?string, payload: array<string, mixed>} $snapshot
     */
    private function record(
        array $snapshot,
        NotificationInterface $notification,
        NotificationSendResult $result,
        ?Throwable $exception,
    ): void {
        if (!$this->toggle->isEnabled()) {
            return;
        }

        $actor   = $this->actorResolver->resolve();
        $payload = $snapshot['payload'];
        if ($result->message() !== null) {
            $payload['result_message'] = $result->message();
        }
        if ($exception !== null) {
            $payload['exception'] = [
                'class'   => $exception::class,
                'message' => $exception->getMessage(),
            ];
        }

        $this->store->store(
            recipientUserId: $snapshot['recipient_user_id'],
            recipientTarget: $snapshot['recipient_target'],
            channel: $this->name(),
            status: $result->status(),
            notificationType: get_class($notification),
            title: $snapshot['title'],
            body: $snapshot['body'],
            payload: $payload,
            senderUserId: $actor->userId,
            senderName: $actor->name,
        );
    }

    private function resolveRecipientUserId(NotifiableInterface $notifiable): ?int
    {
        try {
            $recipient = $notifiable->routeNotificationFor(NotificationChannelNames::DATABASE->value);
            if (is_int($recipient) && $recipient > 0) {
                return $recipient;
            }

            if (is_numeric($recipient)) {
                $recipientId = (int) $recipient;
                if ($recipientId > 0) {
                    return $recipientId;
                }
            }
        } catch (Throwable) {
        }

        return null;
    }

    private function resolveRecipientTarget(
        string $channel,
        NotifiableInterface $notifiable,
        NotificationInterface $notification,
    ): ?string {
        $driver = null;
        if ($channel === NotificationChannelNames::TELEGRAM->value && $notification instanceof TelegramNotificationInterface) {
            try {
                $message = $notification->toTelegram($notifiable);
                $driver  = $message->botName();
            } catch (Throwable) {
                $driver = null;
            }
        }

        try {
            $target = $notifiable->routeNotificationFor($channel, $driver);
        } catch (Throwable) {
            return null;
        }

        if (is_scalar($target)) {
            $value = trim((string) $target);

            return $value !== '' ? $value : null;
        }

        if ($target === null) {
            return null;
        }

        try {
            return json_encode($target, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return null;
        }
    }

    private function extractEmailBody(EmailMessage $message): ?string
    {
        $text = $message->textBody();
        if ($text !== null && trim($text) !== '') {
            return $text;
        }

        $markdown = $message->markdownBody();
        if ($markdown !== null && trim($markdown) !== '') {
            return $markdown;
        }

        $html = $message->htmlBody();
        if ($html !== null && trim($html) !== '') {
            return $html;
        }

        return null;
    }
}
