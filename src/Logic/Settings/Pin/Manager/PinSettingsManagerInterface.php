<?php

namespace App\Logic\Settings\Pin\Manager;

use App\Logic\Settings\Pin\Model\PinSettings;

interface PinSettingsManagerInterface
{
    public function get(): PinSettings;

    public function save(PinSettings $settings): PinSettings;
}
