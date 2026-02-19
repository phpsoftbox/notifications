# Notifications

Компонент уведомлений для PhpSoftBox: менеджер, каналы, драйверы (Email/Telegram/Database) и CLI.

## Быстрый старт

```php
use PhpSoftBox\Notifications\NotificationManager;
use PhpSoftBox\Notifications\NotificationChannelNames as Channels;

$manager = new NotificationManager([
    $emailChannel,
    $telegramChannel,
    $databaseChannel,
]);

$manager->send($user, new WelcomeNotification());
```

Уведомление определяет каналы и условия отправки:

```php
use PhpSoftBox\Notifications\Notification;
use PhpSoftBox\Notifications\NotifiableInterface;
use PhpSoftBox\Notifications\NotificationChannelNames as Channels;

final class WelcomeNotification extends Notification
{
    public function via(NotifiableInterface $notifiable): array
    {
        return [Channels::EMAIL, Channels::DATABASE];
    }

    public function shouldSend(NotifiableInterface $notifiable, string $channel): bool
    {
        return $channel !== Channels::EMAIL || $notifiable->routeNotificationFor(Channels::EMAIL) !== null;
    }
}
```

## NotifiableInterface

Источник получателя определяет модель:

```php
use PhpSoftBox\Notifications\NotifiableInterface;
use PhpSoftBox\Notifications\NotificationChannelNames as Channels;

final class User implements NotifiableInterface
{
    public function routeNotificationFor(string $channel, ?string $driver = null): mixed
    {
        return match ($channel) {
            Channels::EMAIL => $this->email,
            Channels::TELEGRAM => $this->telegramChats[$driver] ?? null,
            Channels::DATABASE => $this->id,
            Channels::PUSHR => $this->pushrChannels[$driver ?? 'default'] ?? null,
            default => null,
        };
    }
}
```

## Email

Email-уведомление реализует `EmailNotificationInterface`:

```php
use PhpSoftBox\Notifications\Email\EmailNotificationInterface;
use PhpSoftBox\Mailer\Message\EmailMessage;

final class WelcomeNotification extends Notification implements EmailNotificationInterface
{
    public function toEmail(NotifiableInterface $notifiable): EmailMessage
    {
        return EmailMessage::create('Добро пожаловать')
            ->markdown('Спасибо за регистрацию.');
    }
}
```

### SMTP транспорт

Для SMTP можно использовать отдельный пакет `phpsoftbox/mailer`:

```php
use PhpSoftBox\Mailer\Smtp\SmtpClient;
use PhpSoftBox\Mailer\Smtp\SmtpClientConfig;
use PhpSoftBox\Mailer\Transport\SmtpEmailTransport;
use PhpSoftBox\Notifications\Email\EmailChannel;

$smtp = new SmtpClient(new SmtpClientConfig(
    host: 'mailhog',
    port: 1025,
    encryption: 'none',
));

$transport = new SmtpEmailTransport($smtp);
$emailChannel = new EmailChannel($transport, null, null, 'no-reply@example.com');
```

Поддерживаются шаблоны (через `PhpSoftBox\View\ViewRendererInterface`) и Markdown-конвертация (`MarkdownToHtmlConverterInterface`):

```php
return EmailMessage::create('Сводка')
    ->template('emails/summary', ['items' => $items], isMarkdown: true);
```

Для HTML-шаблонов можно централизованно оборачивать контент в layout:

```php
return EmailMessage::create('Подтверждение email')
    ->template('email/confirm-email', [
        'name' => $userName,
    ])
    ->layout('email/layout.phtml', [
        'title' => 'Подтверждение email',
        'preview' => 'Подтвердите email, чтобы завершить регистрацию.',
        'actionUrl' => $confirmUrl,
        'actionLabel' => 'Подтвердить email',
    ]);
```

Для `template('email/confirm-email')` канал автоматически рендерит (при renderer с поддержкой `TemplateExistsInterface`, например `PhpViewRenderer`):

- HTML: `email/confirm-email.phtml`
- text (если файл существует рядом): `email/confirm-email.text.phtml`

Legacy-вариант с `layout(array $data)` удалён: используйте `layout(string $template, array|object $data = [])`.

Для object-layout используйте DTO, реализующий `PhpSoftBox\View\LayoutTemplateDataInterface`,
чтобы канал мог безопасно подставить `content`/`title`:

```php
use PhpSoftBox\View\LayoutTemplateDataInterface;

final readonly class EmailLayoutView implements LayoutTemplateDataInterface
{
    public function __construct(
        public ?string $title = null,
        public string $content = '',
        public ?string $preview = null,
    ) {
    }

    public function withLayoutContent(string $content, ?string $defaultTitle = null): object
    {
        return new self(
            title: $this->title ?? $defaultTitle,
            content: $content,
            preview: $this->preview,
        );
    }
}
```

## Telegram

Telegram-канал использует `TelegramBotRegistry`. Бот выбирается через `TelegramMessage::bot()`.

```php
use PhpSoftBox\Notifications\Telegram\TelegramNotificationInterface;
use PhpSoftBox\Notifications\Message\TelegramMessage;

public function toTelegram(NotifiableInterface $notifiable): TelegramMessage
{
    return TelegramMessage::create('Ваш код подтверждения: 1234')
        ->bot('account')
        ->options(['parse_mode' => 'HTML']);
}
```

`routeNotificationFor(Channels::TELEGRAM, $botName)` должен вернуть `chat_id` для выбранного бота.

## Database

Database-канал сохраняет адресные уведомления в таблицу `notifications`.

```php
use PhpSoftBox\Notifications\Database\DatabaseNotificationInterface;
use PhpSoftBox\Notifications\Message\DatabaseMessage;

public function toDatabase(NotifiableInterface $notifiable): DatabaseMessage
{
    return DatabaseMessage::create('Новое уведомление')
        ->body('Текст уведомления')
        ->data(['level' => 'info']);
}
```

Репозиторий поддерживает чтение:

```php
$items = $repository->listForUser($userId, 5);
$unread = $repository->countUnread($userId);
```

## Pushr (WebSocket)

Pushr-канал публикует событие в WebSocket (Pushr).

```php
use PhpSoftBox\Notifications\Pushr\PushrNotificationInterface;
use PhpSoftBox\Notifications\Message\PushrMessage;
use PhpSoftBox\Notifications\Pushr\PushrChannel;
use PhpSoftBox\Notifications\Pushr\PushrPublisherAdapter;
use PhpSoftBox\Broadcaster\Pushr\PushrPublisher;

$pushrChannel = new PushrChannel(
    new PushrPublisherAdapter(new PushrPublisher($appId, $secret, $host)),
);

final class SystemAlertNotification extends Notification implements PushrNotificationInterface
{
    public function toPushr(NotifiableInterface $notifiable): PushrMessage
    {
        return new PushrMessage(
            event: 'notification.created',
            payload: [
                'id' => 10,
                'title' => 'Система',
                'body' => 'Готово.',
            ],
            driver: 'admin',
        );
    }
}
```

`routeNotificationFor(Channels::PUSHR, $driver)` должен вернуть канал (например, `admin.private.user.{id}`).

## Аудит отправок (NotificationAuditChannel)

`NotificationAuditChannel` оборачивает любой канал и пишет историю отправок в ваше хранилище
(обычно в таблицу БД). Это удобно для админских журналов и разборов инцидентов.

В пакет входят:

- `PhpSoftBox\Notifications\Audit\NotificationAuditChannel`
- `PhpSoftBox\Notifications\Audit\NotificationAuditActor`
- `PhpSoftBox\Notifications\Contracts\NotificationAuditStoreInterface`
- `PhpSoftBox\Notifications\Contracts\NotificationAuditActorResolverInterface`
- `PhpSoftBox\Notifications\Contracts\NotificationAuditToggleInterface`

### Как подключить

```php
use PhpSoftBox\Notifications\Audit\NotificationAuditChannel;
use PhpSoftBox\Notifications\NotificationManager;

$manager = new NotificationManager([
    new NotificationAuditChannel($emailChannel, $auditStore, $actorResolver, $toggle),
    new NotificationAuditChannel($telegramChannel, $auditStore, $actorResolver, $toggle),
    new NotificationAuditChannel($databaseChannel, $auditStore, $actorResolver, $toggle),
]);
```

Где:

- `$auditStore` реализует `NotificationAuditStoreInterface` и сохраняет запись (БД, файл, внешняя система).
- `$actorResolver` реализует `NotificationAuditActorResolverInterface` и возвращает отправителя (`userId`, `name`).
- `$toggle` реализует `NotificationAuditToggleInterface` и включает/выключает аудит (например, через конфиг/настройки).

### Какие данные пишет аудит

Базово:

- `recipientUserId` — адресат в терминах вашей системы (берётся через `routeNotificationFor('database')`, если доступен).
- `recipientTarget` — канал доставки (email, `chat_id`, pushr-channel и т.п.).
- `channel`, `status`, `notificationType`.
- `title`, `body`, `payload`.
- `senderUserId`, `senderName`.

Дополнительно по каналам:

- Email: subject/body, служебные поля (`to`, `cc`, `bcc`, `from`, `reply_to`, `template`).
- Telegram: текст сообщения, имя бота, options.
- Database: title/body/data из `DatabaseMessage`.

Если `toggle->isEnabled()` возвращает `false`, отправка работает как обычно, но история не пишется.

## CLI
.
```
make:notification App\\Notifications\\WelcomeNotification
notifications:prune --days=30 --read=1
```
