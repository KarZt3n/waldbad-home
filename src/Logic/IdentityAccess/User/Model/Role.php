<?php

namespace App\Logic\IdentityAccess\User\Model;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
}
