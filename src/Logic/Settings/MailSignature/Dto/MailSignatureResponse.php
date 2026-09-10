<?php

namespace App\Logic\Settings\MailSignature\Dto;

use App\Logic\Settings\MailSignature\Model\MailSignature;

readonly class MailSignatureResponse
{
    public function __construct(
        public string $id,
        public string $name,
        public string $body,
    ) {
    }

    public static function fromSignature(MailSignature $signature): self
    {
        return new self($signature->id, $signature->name, $signature->body);
    }
}
