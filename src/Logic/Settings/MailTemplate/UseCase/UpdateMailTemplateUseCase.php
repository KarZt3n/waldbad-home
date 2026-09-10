<?php

namespace App\Logic\Settings\MailTemplate\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;
use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplateKey;

readonly class UpdateMailTemplateUseCase
{
    public function __construct(
        private MailTemplateManagerInterface $manager,
        private MailSignatureManagerInterface $signatures,
    ) {
    }

    public function execute(MailTemplateKey $key, string $subject, string $body, ?string $signatureId): void
    {
        $subject = trim($subject);
        $body = trim($body);
        if ($subject === '' || $body === '') {
            throw new BusinessRuleViolationException('Betreff und Text dürfen nicht leer sein.');
        }
        if ($signatureId !== null && $this->signatures->find($signatureId) === null) {
            throw new BusinessRuleViolationException('Die ausgewählte Signatur wurde nicht gefunden.');
        }

        $this->manager->save($this->manager->resolve($key)->withText($subject, $body, $signatureId));
    }
}
