<?php

namespace App\Logic\IdentityAccess\Authentication\Query;

use App\Logic\IdentityAccess\Authentication\Dto\AuthenticationIdentity;
use App\Logic\IdentityAccess\User\Manager\UserManagerInterface;

readonly class GetAuthenticationIdentityQuery
{
    public function __construct(private UserManagerInterface $manager)
    {
    }

    public function execute(string $email): AuthenticationIdentity
    {
        $user = $this->manager->getByEmail(mb_strtolower(trim($email)));

        return new AuthenticationIdentity(
            id: $user->id,
            email: $user->email,
            displayName: $user->displayName,
            passwordHash: $user->passwordHash,
            roles: $user->roles,
            moduleAccess: $user->moduleAccess,
            active: $user->active,
            pageAccess: $user->pageAccess,
        );
    }
}
