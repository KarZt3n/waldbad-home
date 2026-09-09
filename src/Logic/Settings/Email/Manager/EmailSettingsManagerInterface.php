<?php

namespace App\Logic\Settings\Email\Manager;

use App\Logic\Settings\Email\Model\EmailSettings;

interface EmailSettingsManagerInterface
{
    public function get(): EmailSettings;

    public function save(EmailSettings $settings): EmailSettings;
}
