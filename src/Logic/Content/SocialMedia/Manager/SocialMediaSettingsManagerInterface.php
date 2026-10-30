<?php

namespace App\Logic\Content\SocialMedia\Manager;

use App\Logic\Content\SocialMedia\Model\SocialMediaSettings;

interface SocialMediaSettingsManagerInterface
{
    public function get(): SocialMediaSettings;

    public function save(SocialMediaSettings $settings): SocialMediaSettings;
}
