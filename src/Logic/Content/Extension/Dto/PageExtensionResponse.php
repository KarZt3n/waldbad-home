<?php

namespace App\Logic\Content\Extension\Dto;

readonly class PageExtensionResponse
{
    /**
     * @param list<PageExtensionTextFieldResponse> $fields
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public array $fields,
    ) {
    }
}
