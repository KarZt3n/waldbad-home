<?php

namespace App\Logic\Contact\Request\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

class ContactRequestNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Die Kontaktanfrage "%s" wurde nicht gefunden.', $id));
    }
}
