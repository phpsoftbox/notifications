<?php

declare(strict_types=1);

namespace PhpSoftBox\Notifications\Email;

interface MarkdownToHtmlConverterInterface
{
    public function convert(string $markdown): string;
}
