<?php

namespace App\Logic\Settings\MailSignature;

use App\Logic\Settings\MailSignature\Model\MailSignature;

interface MailSignatureProcessorInterface
{
    public function save(MailSignature $signature): MailSignature;

    public function delete(string $id): void;
}
