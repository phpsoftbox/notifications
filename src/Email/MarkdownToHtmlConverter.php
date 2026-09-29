<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Email;

use Parsedown;

use function class_exists;
use function htmlspecialchars;
use function nl2br;
use function preg_replace;
use function trim;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Markdown → HTML для писем. С установленным `erusev/parsedown` использует его, иначе — минимальный встроенный
 * конвертер (экранирование HTML, `**жирный**`, `*курсив*`, переводы строк).
 *
 * По умолчанию сырой HTML в Markdown экранируется, а ссылки `javascript:` и т. п. обезвреживаются (safe mode
 * Parsedown): в Markdown-шаблон часто подставляются пользовательские данные без экранирования.
 * `allowRawHtml: true` — только для полностью доверенного Markdown.
 */
final readonly class MarkdownToHtmlConverter implements MarkdownToHtmlConverterInterface
{
    public function __construct(
        private bool $allowRawHtml = false,
    ) {
    }

    public function convert(string $markdown): string
    {
        $markdown = trim($markdown);
        if ($markdown === '') {
            return '';
        }

        if (class_exists('Parsedown')) {
            $parser = new Parsedown();

            $parser->setSafeMode(!$this->allowRawHtml);

            return $parser->text($markdown);
        }

        $escaped = htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped) ?? $escaped;

        return nl2br($escaped);
    }
}
