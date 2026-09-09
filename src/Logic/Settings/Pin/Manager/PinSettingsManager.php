<?php

namespace App\Logic\Settings\Pin\Manager;

use App\Logic\Settings\Pin\Model\PinSettings;
use App\Logic\Settings\Pin\PinSettingsProcessorInterface;
use App\Logic\Settings\Pin\PinSettingsProviderInterface;

readonly class PinSettingsManager implements PinSettingsManagerInterface
{
    public function __construct(
        private PinSettingsProviderInterface $provider,
        private PinSettingsProcessorInterface $processor,
    ) {
    }

    public function get(): PinSettings
    {
        return $this->provider->get();
    }

    public function save(PinSettings $settings): PinSettings
    {
        return $this->processor->save($settings);
    }
}
