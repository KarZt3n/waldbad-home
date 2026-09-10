<?php

namespace App\Logic\Settings\MailSignature\UseCase;

use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;

readonly class DeleteMailSignatureUseCase
{
    public function __construct(private MailSignatureManagerInterface $manager)
    {
    }

    public function execute(string $id): void
    {
        $this->manager->get($id);
        $this->manager->delete($id);
    }
}
