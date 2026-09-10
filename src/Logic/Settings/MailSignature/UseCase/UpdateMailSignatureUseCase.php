<?php

namespace App\Logic\Settings\MailSignature\UseCase;

use App\Logic\Settings\MailSignature\Dto\MailSignatureResponse;
use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;

readonly class UpdateMailSignatureUseCase
{
    public function __construct(private MailSignatureManagerInterface $manager)
    {
    }

    public function execute(string $id, string $name, string $body): MailSignatureResponse
    {
        $signature = $this->manager->get($id)->withText($name, $body);

        return MailSignatureResponse::fromSignature($this->manager->save($signature));
    }
}
