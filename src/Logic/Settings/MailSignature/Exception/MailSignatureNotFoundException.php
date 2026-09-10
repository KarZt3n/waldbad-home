<?php

namespace App\Logic\Settings\MailSignature\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class MailSignatureNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Die Signatur "%s" wurde nicht gefunden.', $id));
    }
}
