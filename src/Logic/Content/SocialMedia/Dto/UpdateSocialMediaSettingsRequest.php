<?php

namespace App\Logic\Content\SocialMedia\Dto;

readonly class UpdateSocialMediaSettingsRequest
{
    /**
     * @param list<SocialMediaLinkRequest> $links in Anzeigereihenfolge
     */
    public function __construct(
        public bool $visible,
        public array $links,
    ) {
    }
}
