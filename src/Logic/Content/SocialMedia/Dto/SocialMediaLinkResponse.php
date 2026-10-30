<?php

namespace App\Logic\Content\SocialMedia\Dto;

readonly class SocialMediaLinkResponse
{
    public function __construct(
        public string $platform,
        public string $label,
        public string $url,
    ) {
    }
}
