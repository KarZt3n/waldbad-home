<?php

namespace App\Logic\Content\SocialMedia;

use App\Logic\Content\SocialMedia\Model\SocialMediaSettings;

interface SocialMediaSettingsProviderInterface
{
    /**
     * Liefert immer einen Datensatz, auch wenn noch nie gespeichert wurde (dann ausgeblendet und
     * ohne Links).
     */
    public function get(): SocialMediaSettings;
}
