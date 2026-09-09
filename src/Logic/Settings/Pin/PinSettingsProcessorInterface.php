<?php

namespace App\Logic\Settings\Pin;

use App\Logic\Settings\Pin\Model\PinSettings;

interface PinSettingsProcessorInterface
{
    public function save(PinSettings $settings): PinSettings;
}
