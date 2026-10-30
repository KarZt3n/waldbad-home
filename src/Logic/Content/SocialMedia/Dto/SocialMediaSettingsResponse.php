<?php

namespace App\Logic\Content\SocialMedia\Dto;

readonly class SocialMediaSettingsResponse
{
    /**
     * @param list<SocialMediaLinkResponse> $links
     */
    public function __construct(
        public bool $visible,
        public array $links,
    ) {
    }
}
