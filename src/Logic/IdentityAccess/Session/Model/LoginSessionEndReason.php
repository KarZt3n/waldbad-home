<?php

namespace App\Logic\IdentityAccess\Session\Model;

enum LoginSessionEndReason: string
{
    case Logout = 'logout';
    case Inactivity = 'inactivity';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
