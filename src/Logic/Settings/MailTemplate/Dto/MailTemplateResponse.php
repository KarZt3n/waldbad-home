<?php

namespace App\Logic\Settings\MailTemplate\Dto;

readonly class MailTemplateResponse
{
    /**
     * @param list<string> $placeholders
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public array $placeholders,
        public string $subject,
        public string $body,
        public ?string $signatureId,
        public bool $isDefault,
    ) {
    }
}
