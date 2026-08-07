<?php

namespace App\Logic\IdentityAccess\User\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

class UserNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $identifier)
    {
        parent::__construct(sprintf('Der Benutzer "%s" wurde nicht gefunden.', $identifier));
    }
}
