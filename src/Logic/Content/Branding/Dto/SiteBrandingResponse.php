<?php

namespace App\Logic\Content\Branding\Dto;

readonly class SiteBrandingResponse
{
    public function __construct(
        public string $logoUrl,
        public ?string $customLogoUrl,
        public string $defaultLogoUrl,
    ) {
    }
}
