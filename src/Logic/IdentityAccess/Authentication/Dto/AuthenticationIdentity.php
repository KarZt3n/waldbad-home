<?php

namespace App\Logic\IdentityAccess\Authentication\Dto;

use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\PageAccess;

readonly class AuthenticationIdentity
{
    /**
     * @param list<Role> $roles
     * @param list<ModuleAccess> $moduleAccess
     * @param list<PageAccess>|null $pageAccess
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public array $roles,
        public array $moduleAccess,
        public bool $active,
        public ?array $pageAccess,
    ) {
    }
}
