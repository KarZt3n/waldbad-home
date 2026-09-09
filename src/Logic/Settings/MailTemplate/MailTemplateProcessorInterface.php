<?php

namespace App\Logic\Settings\MailTemplate;

use App\Logic\Settings\MailTemplate\Model\MailTemplate;

interface MailTemplateProcessorInterface
{
    public function save(MailTemplate $template): MailTemplate;
}
