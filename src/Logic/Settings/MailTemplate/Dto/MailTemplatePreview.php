<?php

namespace App\Logic\Settings\MailTemplate\Dto;

readonly class MailTemplatePreview
{
    public function __construct(
        public string $subject,
        public string $text,
        public string $html,
    ) {
    }
}
