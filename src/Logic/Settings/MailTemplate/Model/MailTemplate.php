<?php

namespace App\Logic\Settings\MailTemplate\Model;

readonly class MailTemplate
{
    public function __construct(
        public MailTemplateKey $key,
        public string $subject,
        public string $body,
    ) {
    }

    public function isDefault(): bool
    {
        return $this->subject === $this->key->defaultSubject() && $this->body === $this->key->defaultBody();
    }

    public function withText(string $subject, string $body): self
    {
        return new self($this->key, $subject, $body);
    }
}
