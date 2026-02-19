<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Tests;

use PhpSoftBox\Mailer\Email\EmailPayload;
use PhpSoftBox\Mailer\Email\EmailTransportInterface;
use PhpSoftBox\Mailer\Message\EmailMessage;
use PhpSoftBox\Notifications\Email\EmailChannel;
use PhpSoftBox\Notifications\Email\EmailNotificationInterface;
use PhpSoftBox\Notifications\Email\MarkdownToHtmlConverterInterface;
use PhpSoftBox\Notifications\NotifiableInterface;
use PhpSoftBox\Notifications\Notification;
use PhpSoftBox\Notifications\NotificationChannelNames;
use PhpSoftBox\View\LayoutTemplateDataInterface;
use PhpSoftBox\View\TemplateExistsInterface;
use PhpSoftBox\View\ViewRendererInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function is_array;

#[CoversClass(EmailChannel::class)]
#[CoversClass(EmailMessage::class)]
#[CoversClass(EmailPayload::class)]
#[CoversMethod(EmailChannel::class, 'send')]
final class EmailChannelTest extends TestCase
{
    /**
     * Проверяет, что email-канал формирует payload из markdown и отдаёт его транспорту.
     */
    #[Test]
    public function testEmailChannelBuildsPayloadFromMarkdown(): void
    {
        $transport = new class () implements EmailTransportInterface {
            public ?EmailPayload $payload = null;

            public function send(EmailMessage $message, EmailPayload $payload): void
            {
                $this->payload = $payload;
            }
        };

        $converter = new class () implements MarkdownToHtmlConverterInterface {
            public function convert(string $markdown): string
            {
                return '<p>converted</p>';
            }
        };

        $channel = new EmailChannel($transport, $converter, null, 'no-reply@example.com');

        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel, ?string $driver = null): mixed
            {
                return 'user@example.com';
            }
        };

        $notification = new class () extends Notification implements EmailNotificationInterface {
            public function via(NotifiableInterface $notifiable): array
            {
                return [NotificationChannelNames::EMAIL->value];
            }

            public function toEmail(NotifiableInterface $notifiable): EmailMessage
            {
                return EmailMessage::create('Hello')->markdown('**test**');
            }
        };

        $result = $channel->send($notifiable, $notification);

        $this->assertTrue($result->isSent());
        $this->assertNotNull($transport->payload);
        $this->assertSame(['user@example.com'], $transport->payload->to);
        $this->assertSame('Hello', $transport->payload->subject);
        $this->assertSame('<p>converted</p>', $transport->payload->html);
        $this->assertSame('no-reply@example.com', $transport->payload->from);
    }

    /**
     * Проверяет, что список email из routeNotificationFor нормализуется и очищается от пустых значений.
     */
    #[Test]
    public function testEmailChannelBuildsRecipientsFromRouteArray(): void
    {
        $transport = new class () implements EmailTransportInterface {
            public ?EmailPayload $payload = null;

            public function send(EmailMessage $message, EmailPayload $payload): void
            {
                $this->payload = $payload;
            }
        };

        $channel = new EmailChannel($transport, null, null, 'no-reply@example.com');

        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel, ?string $driver = null): mixed
            {
                return ['user@example.com', '', null, 'support@example.com'];
            }
        };

        $notification = new class () extends Notification implements EmailNotificationInterface {
            public function via(NotifiableInterface $notifiable): array
            {
                return [NotificationChannelNames::EMAIL->value];
            }

            public function toEmail(NotifiableInterface $notifiable): EmailMessage
            {
                return EmailMessage::create('Hello')->text('Plain text body');
            }
        };

        $result = $channel->send($notifiable, $notification);

        $this->assertTrue($result->isSent());
        $this->assertNotNull($transport->payload);
        $this->assertSame(['user@example.com', 'support@example.com'], $transport->payload->to);
    }

    /**
     * Проверяет, что при отсутствии layout-шаблона контент рендерится напрямую.
     */
    #[Test]
    public function testEmailChannelRendersTemplateWithoutLayout(): void
    {
        $transport = new class () implements EmailTransportInterface {
            public ?EmailPayload $payload = null;

            public function send(EmailMessage $message, EmailPayload $payload): void
            {
                $this->payload = $payload;
            }
        };

        $templateRenderer = new class () implements ViewRendererInterface {
            public string $template = '';
            /** @var array<string, mixed>|object */
            public array|object $data = [];

            public function render(string $template, array|object $data = []): string
            {
                $this->template = $template;
                $this->data     = $data;

                return '<p>ok</p>';
            }

            public function partialRender(string $template, array|object $data = []): string
            {
                return $this->render($template, $data);
            }
        };

        $channel = new EmailChannel($transport, null, $templateRenderer, 'no-reply@example.com');

        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel, ?string $driver = null): mixed
            {
                return 'user@example.com';
            }
        };

        $notification = new class () extends Notification implements EmailNotificationInterface {
            public function via(NotifiableInterface $notifiable): array
            {
                return [NotificationChannelNames::EMAIL->value];
            }

            public function toEmail(NotifiableInterface $notifiable): EmailMessage
            {
                return EmailMessage::create('Template mail')
                    ->template('email/test.php', ['code' => '123456']);
            }
        };

        $result = $channel->send($notifiable, $notification);

        $this->assertTrue($result->isSent());
        $this->assertSame('email/test.php', $templateRenderer->template);
        $this->assertSame('123456', $templateRenderer->data['code'] ?? null);
        $this->assertSame('<p>ok</p>', $transport->payload?->html);
    }

    /**
     * Проверяет, что при указании layout-шаблона контент рендерится через layout на уровне канала.
     */
    #[Test]
    public function testEmailChannelWrapsTemplateIntoLayoutTemplate(): void
    {
        $transport = new class () implements EmailTransportInterface {
            public ?EmailPayload $payload = null;

            public function send(EmailMessage $message, EmailPayload $payload): void
            {
                $this->payload = $payload;
            }
        };

        $templateRenderer = new class () implements ViewRendererInterface {
            /** @var list<string> */
            public array $templates = [];
            /** @var list<array<string, mixed>|object> */
            public array $payloads = [];

            public function render(string $template, array|object $data = []): string
            {
                $this->templates[] = $template;
                $this->payloads[]  = $data;

                $content = is_array($data) ? ($data['content'] ?? '') : ($data->content ?? '');

                return $template === 'email/layout.phtml'
                    ? '<html><body>' . (string) $content . '</body></html>'
                    : '<p>content</p>';
            }

            public function partialRender(string $template, array|object $data = []): string
            {
                return $this->render($template, $data);
            }
        };

        $channel = new EmailChannel($transport, null, $templateRenderer, 'no-reply@example.com');

        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel, ?string $driver = null): mixed
            {
                return 'user@example.com';
            }
        };

        $notification = new class () extends Notification implements EmailNotificationInterface {
            public function via(NotifiableInterface $notifiable): array
            {
                return [NotificationChannelNames::EMAIL->value];
            }

            public function toEmail(NotifiableInterface $notifiable): EmailMessage
            {
                return EmailMessage::create('Layout mail')
                    ->template('email/content.phtml', ['foo' => 'bar'])
                    ->layout('email/layout.phtml', ['preview' => 'Preview text']);
            }
        };

        $result = $channel->send($notifiable, $notification);

        $this->assertTrue($result->isSent());
        $this->assertNotNull($transport->payload);
        $this->assertSame(['email/content.phtml', 'email/layout.phtml'], $templateRenderer->templates);
        $this->assertSame('bar', $templateRenderer->payloads[0]['foo'] ?? null);
        $this->assertSame('<p>content</p>', $templateRenderer->payloads[1]['content'] ?? null);
        $this->assertSame('Layout mail', $templateRenderer->payloads[1]['title'] ?? null);
        $this->assertSame('Preview text', $templateRenderer->payloads[1]['preview'] ?? null);
        $this->assertSame('<html><body><p>content</p></body></html>', $transport->payload->html);
    }

    /**
     * Проверяет, что layout-DTO реализующий LayoutTemplateDataInterface получает content/title.
     */
    #[Test]
    public function testEmailChannelWrapsTemplateIntoLayoutDto(): void
    {
        $transport = new class () implements EmailTransportInterface {
            public ?EmailPayload $payload = null;

            public function send(EmailMessage $message, EmailPayload $payload): void
            {
                $this->payload = $payload;
            }
        };

        $templateRenderer = new class () implements ViewRendererInterface {
            /** @var list<array<string, mixed>|object> */
            public array $payloads = [];

            public function render(string $template, array|object $data = []): string
            {
                $this->payloads[] = $data;

                if ($template === 'email/layout.phtml') {
                    return '<html><body>' . (string) ($data->content ?? '') . '</body></html>';
                }

                return '<p>dto-content</p>';
            }

            public function partialRender(string $template, array|object $data = []): string
            {
                return $this->render($template, $data);
            }
        };

        $channel = new EmailChannel($transport, null, $templateRenderer, 'no-reply@example.com');

        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel, ?string $driver = null): mixed
            {
                return 'user@example.com';
            }
        };

        $notification = new class () extends Notification implements EmailNotificationInterface {
            public function via(NotifiableInterface $notifiable): array
            {
                return [NotificationChannelNames::EMAIL->value];
            }

            public function toEmail(NotifiableInterface $notifiable): EmailMessage
            {
                return EmailMessage::create('Layout DTO mail')
                    ->template('email/content.phtml', ['foo' => 'bar'])
                    ->layout('email/layout.phtml', new class ('Preview text') implements LayoutTemplateDataInterface {
                        public function __construct(
                            public string $preview,
                            public ?string $title = null,
                            public string $content = '',
                        ) {
                        }

                        public function withLayoutContent(string $content, ?string $defaultTitle = null): object
                        {
                            return new self(
                                preview: $this->preview,
                                title: $this->title ?? $defaultTitle,
                                content: $content,
                            );
                        }
                    });
            }
        };

        $result = $channel->send($notifiable, $notification);

        $this->assertTrue($result->isSent());
        $this->assertNotNull($transport->payload);
        $layoutPayload = $templateRenderer->payloads[1];
        $this->assertTrue($layoutPayload instanceof LayoutTemplateDataInterface);
        $this->assertSame('Preview text', $layoutPayload->preview);
        $this->assertSame('Layout DTO mail', $layoutPayload->title);
        $this->assertSame('<p>dto-content</p>', $layoutPayload->content);
        $this->assertSame('<html><body><p>dto-content</p></body></html>', $transport->payload->html);
    }

    /**
     * Проверяет, что для template без расширения используется .phtml и автоматически рендерится *.text.phtml.
     */
    #[Test]
    public function testEmailChannelAutoRendersTextTemplateNearHtmlTemplate(): void
    {
        $transport = new class () implements EmailTransportInterface {
            public ?EmailPayload $payload = null;

            public function send(EmailMessage $message, EmailPayload $payload): void
            {
                $this->payload = $payload;
            }
        };

        $templateRenderer = new class () implements ViewRendererInterface, TemplateExistsInterface {
            /** @var list<string> */
            public array $templates = [];
            /** @var list<array<string, mixed>|object> */
            public array $payloads = [];

            public function render(string $template, array|object $data = []): string
            {
                $this->templates[] = $template;
                $this->payloads[]  = $data;

                return $template === 'email/welcome.text.phtml'
                    ? 'Text body'
                    : '<p>Html body</p>';
            }

            public function partialRender(string $template, array|object $data = []): string
            {
                return $this->render($template, $data);
            }

            public function exists(string $template): bool
            {
                return $template === 'email/welcome.text.phtml';
            }
        };

        $channel = new EmailChannel($transport, null, $templateRenderer, 'no-reply@example.com');

        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel, ?string $driver = null): mixed
            {
                return 'user@example.com';
            }
        };

        $notification = new class () extends Notification implements EmailNotificationInterface {
            public function via(NotifiableInterface $notifiable): array
            {
                return [NotificationChannelNames::EMAIL->value];
            }

            public function toEmail(NotifiableInterface $notifiable): EmailMessage
            {
                return EmailMessage::create('Template mail')
                    ->template('email/welcome', ['name' => 'Anton']);
            }
        };

        $result = $channel->send($notifiable, $notification);

        $this->assertTrue($result->isSent());
        $this->assertNotNull($transport->payload);
        $this->assertSame(['email/welcome.phtml', 'email/welcome.text.phtml'], $templateRenderer->templates);
        $this->assertSame('<p>Html body</p>', $transport->payload->html);
        $this->assertSame('Text body', $transport->payload->text);
    }
}
