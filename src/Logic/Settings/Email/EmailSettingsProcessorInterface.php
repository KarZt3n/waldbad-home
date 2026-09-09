<?php

namespace App\Logic\Settings\Email;

use App\Logic\Settings\Email\Model\EmailSettings;

interface EmailSettingsProcessorInterface
{
    public function save(EmailSettings $settings): EmailSettings;
}
