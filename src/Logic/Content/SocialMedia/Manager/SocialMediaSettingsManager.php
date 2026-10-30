<?php

namespace App\Logic\Content\SocialMedia\Manager;

use App\Logic\Content\SocialMedia\Model\SocialMediaSettings;
use App\Logic\Content\SocialMedia\SocialMediaSettingsProcessorInterface;
use App\Logic\Content\SocialMedia\SocialMediaSettingsProviderInterface;

readonly class SocialMediaSettingsManager implements SocialMediaSettingsManagerInterface
{
    public function __construct(
        private SocialMediaSettingsProviderInterface $provider,
        private SocialMediaSettingsProcessorInterface $processor,
    ) {
    }

    public function get(): SocialMediaSettings
    {
        return $this->provider->get();
    }

    public function save(SocialMediaSettings $settings): SocialMediaSettings
    {
        return $this->processor->save($settings);
    }
}
