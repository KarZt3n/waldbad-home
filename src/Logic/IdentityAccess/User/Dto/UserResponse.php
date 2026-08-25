<?php

namespace App\Logic\IdentityAccess\User\Dto;

use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\Model\User;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;

readonly class UserResponse
{
    /**
     * @param list<Role> $roles
     * @param list<ModuleAccess> $moduleAccess
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public array $roles,
        public array $moduleAccess,
        public bool $active,
        public int $version,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $lastLoginAt,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            email: $user->email,
            displayName: $user->displayName,
            roles: $user->roles,
            moduleAccess: $user->moduleAccess,
            active: $user->active,
            version: $user->version,
            createdAt: $user->createdAt,
            updatedAt: $user->updatedAt,
            lastLoginAt: $user->lastLoginAt,
        );
    }
}
