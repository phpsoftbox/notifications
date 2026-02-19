<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Email;

use PhpSoftBox\Collection\Collection;
use PhpSoftBox\Mailer\Email\EmailPayload;
use PhpSoftBox\Mailer\Email\EmailTransportInterface;
use PhpSoftBox\Notifications\Contracts\NotificationChannelInterface;
use PhpSoftBox\Notifications\NotifiableInterface;
use PhpSoftBox\Notifications\NotificationChannelNames;
use PhpSoftBox\Notifications\NotificationInterface;
use PhpSoftBox\Notifications\NotificationSendResult;
use PhpSoftBox\View\LayoutTemplateDataInterface;
use PhpSoftBox\View\TemplateExistsInterface;
use PhpSoftBox\View\ViewRendererInterface;

use function array_key_exists;
use function array_merge;
use function get_debug_type;
use function interface_exists;
use function is_array;
use function is_string;
use function pathinfo;
use function str_ends_with;
use function strlen;
use function substr;

use const PATHINFO_EXTENSION;

final class EmailChannel implements NotificationChannelInterface
{
    public function __construct(
        private readonly EmailTransportInterface $transport,
        private readonly ?MarkdownToHtmlConverterInterface $markdownConverter = null,
        private readonly ?ViewRendererInterface $templateRenderer = null,
        private readonly ?string $defaultFrom = null,
    ) {
    }

    public function name(): string
    {
        return NotificationChannelNames::EMAIL->value;
    }

    public function isAvailable(): bool
    {
        return interface_exists(EmailTransportInterface::class);
    }

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): NotificationSendResult
    {
        if (!$notification instanceof EmailNotificationInterface) {
            return NotificationSendResult::failed($this->name(), 'Notification does not implement EmailNotificationInterface.');
        }

        $message = $notification->toEmail($notifiable);
        $to      = $message->toAddresses();

        if ($to === []) {
            $route = $notifiable->routeNotificationFor($this->name());
            if (is_string($route) && $route !== '') {
                $to = [$route];
            } elseif (is_array($route)) {
                $to = Collection::from($route)
                    ->map(static fn (mixed $value): string => (string) $value)
                    ->filter(static fn (string $value): bool => $value !== '')
                    ->values()
                    ->all();
            }
        }

        if ($to === []) {
            return NotificationSendResult::skipped($this->name(), 'No email recipients.');
        }

        $subject = $message->subjectText() ?? 'Notification';
        $text    = $message->textBody();
        $html    = $message->htmlBody();

        $template = $message->templateName();
        if ($template !== null) {
            if ($this->templateRenderer === null) {
                return NotificationSendResult::failed($this->name(), 'Template renderer is not configured.');
            }

            $template       = $this->resolveTemplateName($template);
            $templateData   = $message->templateData();
            $layoutData     = $message->layoutData();
            $layoutTemplate = $message->layoutTemplateName();
            $textTemplate   = $this->canRenderTextTemplate($template)
                ? $this->resolveTextTemplateName($template)
                : null;

            $rendered = $this->templateRenderer->render($template, $templateData);
            if ($message->templateIsMarkdown()) {
                $markdown = $rendered;
            } else {
                if ($text === null && $textTemplate !== null && $this->templateExists($textTemplate)) {
                    $text = $this->templateRenderer->render($textTemplate, $templateData);
                }

                if (is_string($layoutTemplate) && $layoutTemplate !== '') {
                    $layoutPayload = $this->prepareLayoutPayload($layoutData, $rendered, $subject);
                    if ($layoutPayload === null) {
                        return NotificationSendResult::failed(
                            $this->name(),
                            'Layout data object must implement ' . LayoutTemplateDataInterface::class . ', got ' . get_debug_type($layoutData) . '.',
                        );
                    }

                    $html = $this->templateRenderer->render($layoutTemplate, $layoutPayload);
                } else {
                    $html = $rendered;
                }
                $markdown = null;
            }
        } else {
            $markdown = $message->markdownBody();
        }

        if ($html === null && $markdown !== null) {
            if ($this->markdownConverter === null) {
                return NotificationSendResult::failed($this->name(), 'Markdown converter is not configured.');
            }
            $html = $this->markdownConverter->convert($markdown);
        }

        $payload = new EmailPayload(
            to: $to,
            cc: $message->ccAddresses(),
            bcc: $message->bccAddresses(),
            from: $message->fromAddress() ?? $this->defaultFrom,
            replyTo: $message->replyToAddress(),
            subject: $subject,
            text: $text,
            html: $html,
        );

        $this->transport->send($message, $payload);

        return NotificationSendResult::sent($this->name());
    }

    /**
     * @param array<string, mixed>|object $layoutData
     * @return array<string, mixed>|object|null
     */
    private function prepareLayoutPayload(array|object $layoutData, string $content, string $subject): array|object|null
    {
        if (is_array($layoutData)) {
            $layoutPayload = array_merge($layoutData, [
                'content' => $content,
            ]);
            if ($subject !== '' && !array_key_exists('title', $layoutPayload)) {
                $layoutPayload['title'] = $subject;
            }

            return $layoutPayload;
        }

        if ($layoutData instanceof LayoutTemplateDataInterface) {
            return $layoutData->withLayoutContent($content, $subject !== '' ? $subject : null);
        }

        return null;
    }

    private function resolveTemplateName(string $template): string
    {
        return $this->hasKnownTemplateExtension($template) ? $template : $template . '.phtml';
    }

    private function resolveTextTemplateName(string $template): string
    {
        $extension = pathinfo($template, PATHINFO_EXTENSION);
        if (!is_string($extension) || $extension === '') {
            return $template . '.text';
        }

        $suffix = '.' . $extension;
        if (!str_ends_with($template, $suffix)) {
            return $template . '.text';
        }

        return substr($template, 0, -strlen($suffix)) . '.text' . $suffix;
    }

    private function hasKnownTemplateExtension(string $template): bool
    {
        $extension = pathinfo($template, PATHINFO_EXTENSION);

        return is_string($extension) && $extension !== '';
    }

    private function canRenderTextTemplate(string $template): bool
    {
        $extension = pathinfo($template, PATHINFO_EXTENSION);

        return is_string($extension) && $extension === 'phtml';
    }

    private function templateExists(string $template): bool
    {
        return $this->templateRenderer instanceof TemplateExistsInterface && $this->templateRenderer->exists($template);
    }
}
