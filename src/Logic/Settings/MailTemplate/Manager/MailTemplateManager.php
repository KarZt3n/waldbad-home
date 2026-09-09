<?php

namespace App\Logic\Settings\MailTemplate\Manager;

use App\Logic\Settings\MailTemplate\MailTemplateProcessorInterface;
use App\Logic\Settings\MailTemplate\MailTemplateProviderInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

readonly class MailTemplateManager implements MailTemplateManagerInterface
{
    public function __construct(
        private MailTemplateProviderInterface $provider,
        private MailTemplateProcessorInterface $processor,
    ) {
    }

    public function resolve(MailTemplateKey $key): MailTemplate
    {
        return $this->provider->find($key) ?? new MailTemplate($key, $key->defaultSubject(), $key->defaultBody());
    }

    public function resolveAll(): array
    {
        $stored = [];
        foreach ($this->provider->findAll() as $template) {
            $stored[$template->key->value] = $template;
        }

        return array_map(
            static fn (MailTemplateKey $key): MailTemplate => $stored[$key->value] ?? new MailTemplate($key, $key->defaultSubject(), $key->defaultBody()),
            MailTemplateKey::cases(),
        );
    }

    public function save(MailTemplate $template): MailTemplate
    {
        return $this->processor->save($template);
    }
}
