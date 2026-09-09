<?php

namespace App\Logic\Settings\MailTemplate;

use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

interface MailTemplateProviderInterface
{
    public function find(MailTemplateKey $key): ?MailTemplate;

    /**
     * @return list<MailTemplate>
     */
    public function findAll(): array;
}
