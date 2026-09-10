<?php

namespace App\Logic\Settings\MailSignature\Query;

use App\Logic\Settings\MailSignature\Dto\MailSignatureResponse;
use App\Logic\Settings\MailSignature\Manager\MailSignatureManagerInterface;

readonly class ListMailSignaturesQuery
{
    public function __construct(private MailSignatureManagerInterface $manager)
    {
    }

    /**
     * @return list<MailSignatureResponse>
     */
    public function execute(): array
    {
        return array_map(MailSignatureResponse::fromSignature(...), $this->manager->list());
    }
}
