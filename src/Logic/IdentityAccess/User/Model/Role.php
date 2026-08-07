<?php

namespace App\Logic\IdentityAccess\User\Model;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Publisher = 'publisher';
    case Editor = 'editor';
    case Moderator = 'moderator';
    case Viewer = 'viewer';
}
