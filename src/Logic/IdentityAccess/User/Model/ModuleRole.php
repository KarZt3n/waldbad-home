<?php

namespace App\Logic\IdentityAccess\User\Model;

enum ModuleRole: string
{
    case Viewer = 'viewer';
    case Editor = 'editor';
    case Publisher = 'publisher';
    case Moderator = 'moderator';
}
