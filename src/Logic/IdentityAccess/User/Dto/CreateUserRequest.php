<?php

namespace App\Logic\IdentityAccess\User\Dto;

use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;

readonly class CreateUserRequest
{
    /**
     * @param list<Role> $roles
     * @param list<ModuleAccess> $moduleAccess
     */
    public function __construct(
        public string $email,
        public string $displayName,
        public string $plainPassword,
        public array $roles,
        public array $moduleAccess,
    ) {
    }
}
