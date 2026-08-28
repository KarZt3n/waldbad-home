<?php

namespace App\Logic\IdentityAccess\Authorization;

use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\PageAccess;
use App\Logic\IdentityAccess\User\Model\Role;

readonly class PageAuthorizationContext
{
    /**
     * @param list<Role> $roles
     * @param list<ModuleAccess> $moduleAccess
     * @param list<PageAccess>|null $pageAccess Null bedeutet uneingeschränkten Seitenzugriff gemäß Modulrolle.
     */
    public function __construct(
        public array $roles,
        public array $moduleAccess,
        public ?array $pageAccess,
    ) {
    }
}
