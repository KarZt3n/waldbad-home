<?php

namespace App\Logic\IdentityAccess\User\Exception;

use App\Logic\Common\Exception\BusinessRuleViolationException;

class UserEmailAlreadyExistsException extends BusinessRuleViolationException
{
    public function __construct()
    {
        parent::__construct('Für diese E-Mail-Adresse existiert bereits ein Benutzer.');
    }
}
