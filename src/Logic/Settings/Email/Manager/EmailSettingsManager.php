<?php

namespace App\Logic\Settings\Email\Manager;

use App\Logic\Settings\Email\EmailSettingsProcessorInterface;
use App\Logic\Settings\Email\EmailSettingsProviderInterface;
use App\Logic\Settings\Email\Model\EmailSettings;

readonly class EmailSettingsManager implements EmailSettingsManagerInterface
{
    public function __construct(
        private EmailSettingsProviderInterface $provider,
        private EmailSettingsProcessorInterface $processor,
    ) {
    }

    public function get(): EmailSettings
    {
        return $this->provider->get();
    }

    public function save(EmailSettings $settings): EmailSettings
    {
        return $this->processor->save($settings);
    }
}
