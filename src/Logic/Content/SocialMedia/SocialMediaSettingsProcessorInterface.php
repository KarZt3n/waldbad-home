<?php

namespace App\Logic\Content\SocialMedia;

use App\Logic\Content\SocialMedia\Model\SocialMediaSettings;

interface SocialMediaSettingsProcessorInterface
{
    public function save(SocialMediaSettings $settings): SocialMediaSettings;
}
