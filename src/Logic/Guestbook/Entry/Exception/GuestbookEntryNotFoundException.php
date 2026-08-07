<?php

namespace App\Logic\Guestbook\Entry\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

class GuestbookEntryNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Der Gästebucheintrag "%s" wurde nicht gefunden.', $id));
    }
}
