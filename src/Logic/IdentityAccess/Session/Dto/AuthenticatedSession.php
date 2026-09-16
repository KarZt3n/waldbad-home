<?php

namespace App\Logic\IdentityAccess\Session\Dto;

use App\Logic\IdentityAccess\User\Model\User;

readonly class AuthenticatedSession
{
    public function __construct(
        public User $user,
        public IssuedSession $tokens,
    ) {
    }
}
