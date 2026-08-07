<?php

namespace App\Logic\IdentityAccess\User\Dto;

use App\Logic\IdentityAccess\User\Model\Role;

readonly class CreateUserRequest
{
    /**
     * @param list<Role> $roles
     */
    public function __construct(
        public string $email,
        public string $displayName,
        public string $plainPassword,
        public array $roles,
    ) {
    }
}
