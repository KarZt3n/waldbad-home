<?php

namespace App\Logic\IdentityAccess\User\Model;

enum PageAccessRole: string
{
    case Editor = 'editor';
    case Publisher = 'publisher';
}
