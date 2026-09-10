<?php

namespace App\Logic\Settings\MailSignature\UseCase;

use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Settings\MailSignature\Dto\MailSignatureResponse;
use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;
use App\Logic\Settings\MailSignature\Model\MailSignature;

readonly class CreateMailSignatureUseCase
{
    public function __construct(
        private MailSignatureManagerInterface $manager,
        private IdentifierGeneratorInterface $identifierGenerator,
    ) {
    }

    public function execute(string $name, string $body): MailSignatureResponse
    {
        $signature = new MailSignature($this->identifierGenerator->generate(), $name, $body);

        return MailSignatureResponse::fromSignature($this->manager->save($signature));
    }
}
