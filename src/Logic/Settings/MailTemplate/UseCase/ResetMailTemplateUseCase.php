<?php

namespace App\Logic\Settings\MailTemplate\UseCase;

use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

readonly class ResetMailTemplateUseCase
{
    public function __construct(private MailTemplateManagerInterface $manager)
    {
    }

    public function execute(MailTemplateKey $key): void
    {
        $this->manager->save($this->manager->resolve($key)->withText($key->defaultSubject(), $key->defaultBody()));
    }
}
