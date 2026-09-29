<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Tests;

use PhpSoftBox\Notifications\Email\MarkdownToHtmlConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkdownToHtmlConverter::class)]
#[CoversMethod(MarkdownToHtmlConverter::class, 'convert')]
final class MarkdownToHtmlConverterTest extends TestCase
{
    /**
     * Проверим, что сырой HTML из подставленных в Markdown данных экранируется по умолчанию.
     *
     * @see MarkdownToHtmlConverter::convert()
     */
    #[Test]
    public function rawHtmlIsEscapedByDefault(): void
    {
        $html = new MarkdownToHtmlConverter()->convert('Компания: <img src=x onerror="alert(1)">');

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img', $html);
    }

    /**
     * Проверим, что ссылка со схемой javascript: обезвреживается.
     *
     * @see MarkdownToHtmlConverter::convert()
     */
    #[Test]
    public function javascriptLinkIsNeutralized(): void
    {
        $html = new MarkdownToHtmlConverter()->convert('[Открыть](javascript:alert(1))');

        self::assertStringNotContainsString('href="javascript:', $html);
    }

    /**
     * Проверим, что для доверенного Markdown сырой HTML можно разрешить явно.
     *
     * @see MarkdownToHtmlConverter::convert()
     */
    #[Test]
    public function rawHtmlIsKeptWhenAllowed(): void
    {
        $html = new MarkdownToHtmlConverter(allowRawHtml: true)->convert('<b>Важно</b>');

        self::assertStringContainsString('<b>Важно</b>', $html);
    }
}
