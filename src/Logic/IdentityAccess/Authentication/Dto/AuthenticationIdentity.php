<?php

namespace App\Logic\IdentityAccess\Authentication\Dto;

use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;

readonly class AuthenticationIdentity
{
    /**
     * @param list<Role> $roles
     * @param list<ModuleAccess> $moduleAccess
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $passwordHash,
        public array $roles,
        public array $moduleAccess,
        public bool $active,
    ) {
    }
}
