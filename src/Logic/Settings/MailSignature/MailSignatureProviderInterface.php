<?php

namespace App\Logic\Settings\MailSignature;

use App\Logic\Settings\MailSignature\Model\MailSignature;

interface MailSignatureProviderInterface
{
    public function find(string $id): ?MailSignature;

    /**
     * @return list<MailSignature>
     */
    public function findAll(): array;
}
