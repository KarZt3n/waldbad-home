<?php

namespace App\Logic\Content\Page;

interface HtmlSanitizerInterface
{
    public function sanitize(string $html): string;

    public function sanitizeInline(string $html): string;
}
