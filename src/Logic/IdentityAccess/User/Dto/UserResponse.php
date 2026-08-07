<?php

namespace App\Logic\IdentityAccess\User\Dto;

use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\Model\User;

readonly class UserResponse
{
    /**
     * @param list<Role> $roles
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public array $roles,
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
            active: $user->active,
            version: $user->version,
            createdAt: $user->createdAt,
            updatedAt: $user->updatedAt,
            lastLoginAt: $user->lastLoginAt,
        );
    }
}
