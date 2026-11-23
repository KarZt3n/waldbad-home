<?php

namespace App\Logic\Content\Footer\Dto;

readonly class FooterLinkResponse
{
    public function __construct(
        public string $label,
        public string $slug,
    ) {
    }
}
