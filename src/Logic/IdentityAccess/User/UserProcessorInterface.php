<?php

namespace App\Logic\IdentityAccess\User;

use App\Logic\IdentityAccess\User\Model\User;

interface UserProcessorInterface
{
    public function save(User $user): User;
}
