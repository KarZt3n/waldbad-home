<?php

namespace App\Logic\Content\SocialMedia\Manager;

use App\Logic\Common\ModelCacheInterface;
use App\Logic\Content\SocialMedia\Model\SocialMediaSettings;
use App\Logic\Content\SocialMedia\SocialMediaSettingsProcessorInterface;
use App\Logic\Content\SocialMedia\SocialMediaSettingsProviderInterface;

/** Die Leiste steht auf jeder öffentlichen Seite und wird deshalb serverseitig gecacht. */
readonly class SocialMediaSettingsManager implements SocialMediaSettingsManagerInterface
{
    private const string CACHE_KEY = 'social_media.settings';

    public function __construct(
        private SocialMediaSettingsProviderInterface $provider,
        private SocialMediaSettingsProcessorInterface $processor,
        private ModelCacheInterface $cache,
    ) {
    }

    public function get(): SocialMediaSettings
    {
        $cached = $this->cache->load(self::CACHE_KEY);
        if ($cached instanceof SocialMediaSettings) {
            return $cached;
        }
        $settings = $this->provider->get();
        $this->cache->store(self::CACHE_KEY, $settings);

        return $settings;
    }

    public function save(SocialMediaSettings $settings): SocialMediaSettings
    {
        $saved = $this->processor->save($settings);
        $this->cache->invalidate(self::CACHE_KEY);

        return $saved;
    }
}
