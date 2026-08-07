<?php

namespace App\Logic\IdentityAccess\Authentication\Dto;

use App\Logic\IdentityAccess\User\Model\Role;

readonly class AuthenticationIdentity
{
    /**
     * @param list<Role> $roles
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $passwordHash,
        public array $roles,
        public bool $active,
    ) {
    }
}
