<?php

namespace App\Logic\Content\SocialMedia\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class SocialMediaLink
{
    public const int MAX_URL_LENGTH = 500;

    public string $url;

    public function __construct(
        public SocialPlatform $platform,
        string $url,
    ) {
        $url = trim($url);
        if ($url === '') {
            throw new BusinessRuleViolationException(sprintf('Für %s fehlt der Link.', $platform->label()));
        }
        if (mb_strlen($url) > self::MAX_URL_LENGTH) {
            throw new BusinessRuleViolationException(sprintf('Der Link für %s darf höchstens %d Zeichen lang sein.', $platform->label(), self::MAX_URL_LENGTH));
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            throw new BusinessRuleViolationException(sprintf('Der Link für %s muss eine vollständige Adresse mit https:// sein.', $platform->label()));
        }
        $this->url = $url;
    }
}
