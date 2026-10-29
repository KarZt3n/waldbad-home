<?php

namespace App\Logic\Content\Extension\Dto;

readonly class PageExtensionTextsResponse
{
    /**
     * @param array<string, string> $texts
     */
    public function __construct(
        public string $key,
        public array $texts,
        public ?string $consentVersion,
    ) {
    }
}
