<?php

namespace App\Logic\IdentityAccess\Session;

use App\Logic\IdentityAccess\Session\Model\LoginSession;

interface LoginSessionProcessorInterface
{
    public function save(LoginSession $session): LoginSession;
}
