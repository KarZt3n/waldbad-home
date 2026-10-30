<?php

namespace App\Logic\Content\SocialMedia\Model;

/**
 * Die Plattformen, die in der Social-Media-Leiste der Website verlinkt werden können. Eine weitere
 * Plattform braucht hier einen neuen Fall sowie ein Icon im Frontend (`SOCIAL_MEDIA_ICONS` in
 * `assets/public/site-chrome.js`).
 */
enum SocialPlatform: string
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case YouTube = 'youtube';
    case TikTok = 'tiktok';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::YouTube => 'YouTube',
            self::TikTok => 'TikTok',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
