<?php

namespace App\Logic\Content\SocialMedia\Dto;

use App\Logic\Content\SocialMedia\Model\SocialPlatform;

readonly class SocialMediaLinkRequest
{
    public function __construct(
        public SocialPlatform $platform,
        public string $url,
    ) {
    }
}
