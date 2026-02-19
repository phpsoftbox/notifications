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

final class MarkdownToHtmlConverter implements MarkdownToHtmlConverterInterface
{
    public function convert(string $markdown): string
    {
        $markdown = trim($markdown);
        if ($markdown === '') {
            return '';
        }

        if (class_exists('Parsedown')) {
            /** @var object $parser */
            $parser = new Parsedown();

            return $parser->text($markdown);
        }

        $escaped = htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped) ?? $escaped;

        return nl2br($escaped);
    }
}
