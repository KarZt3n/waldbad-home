<?php

namespace App\Logic\Content\Extension\Dto;

readonly class PageExtensionTextFieldResponse
{
    public function __construct(
        public string $key,
        public string $label,
        public string $text,
        public string $defaultText,
        public bool $multiline,
        public bool $consent,
        public bool $isDefault,
    ) {
    }
}
