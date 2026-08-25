<?php

namespace App\Data\IdentityAccess\User\Mapper;

use App\Data\IdentityAccess\User\Entity\UserEntity;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\Model\User;

readonly class UserMapper
{
    public function toModel(UserEntity $entity): User
    {
        return new User(
            id: $entity->getId(),
            email: $entity->getEmail(),
            displayName: $entity->getDisplayName(),
            passwordHash: $entity->getPasswordHash(),
            roles: array_map(Role::from(...), $entity->getRoles()),
            moduleAccess: $this->toModuleAccess($entity->getModules()),
            active: $entity->isActive(),
            version: $entity->getVersion(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
            lastLoginAt: $entity->getLastLoginAt(),
        );
    }

    public function createEntity(User $user): UserEntity
    {
        return new UserEntity(
            id: $user->id,
            email: $user->email,
            displayName: $user->displayName,
            passwordHash: $user->passwordHash,
            roles: array_map(static fn (Role $role): string => $role->value, $user->roles),
            modules: $this->toStorage($user->moduleAccess),
            active: $user->active,
            createdAt: $user->createdAt,
            updatedAt: $user->updatedAt,
            lastLoginAt: $user->lastLoginAt,
        );
    }

    public function updateEntity(User $user, UserEntity $entity): void
    {
        $entity->update(
            displayName: $user->displayName,
            passwordHash: $user->passwordHash,
            roles: array_map(static fn (Role $role): string => $role->value, $user->roles),
            modules: $this->toStorage($user->moduleAccess),
            active: $user->active,
            updatedAt: $user->updatedAt,
            lastLoginAt: $user->lastLoginAt,
        );
    }

    /**
     * @param array<string, string> $values
     * @return list<ModuleAccess>
     */
    private function toModuleAccess(array $values): array
    {
        $moduleAccess = [];
        foreach ($values as $module => $role) {
            $moduleAccess[] = new ModuleAccess(CmsModule::from($module), ModuleRole::from($role));
        }

        return $moduleAccess;
    }

    /**
     * @param list<ModuleAccess> $moduleAccess
     * @return array<string, string>
     */
    private function toStorage(array $moduleAccess): array
    {
        $values = [];
        foreach ($moduleAccess as $access) {
            $values[$access->module->value] = $access->role->value;
        }

        return $values;
    }
}
