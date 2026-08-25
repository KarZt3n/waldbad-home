<?php

namespace App\UI\IdentityAccess\Security;

use App\Logic\IdentityAccess\Authentication\Dto\AuthenticationIdentity;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\Role;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

readonly class AuthenticatedUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public function __construct(private AuthenticationIdentity $identity)
    {
    }

    public function getId(): string
    {
        return $this->identity->id;
    }

    public function getDisplayName(): string
    {
        return $this->identity->displayName;
    }

    /**
     * @return list<Role>
     */
    public function getDomainRoles(): array
    {
        return $this->identity->roles;
    }

    public function isActive(): bool
    {
        return $this->identity->active;
    }

    /**
     * @return list<ModuleAccess>
     */
    public function getModuleAccess(): array
    {
        return $this->identity->moduleAccess;
    }

    public function getUserIdentifier(): string
    {
        if ($this->identity->email === '') {
            throw new \LogicException('Eine Authentifizierungsidentität benötigt eine E-Mail-Adresse.');
        }

        return $this->identity->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = array_map(
            static fn (Role $role): string => 'ROLE_'.strtoupper($role->value),
            $this->identity->roles,
        );
        $roles[] = 'ROLE_CMS';
        $roles[] = 'ROLE_CMS_READ';
        $isAdministrator = in_array(Role::Admin, $this->identity->roles, true)
            || in_array(Role::SuperAdmin, $this->identity->roles, true);
        foreach ($this->identity->moduleAccess as $access) {
            $moduleRole = $isAdministrator
                ? ($access->module === CmsModule::Pages ? ModuleRole::Publisher : ModuleRole::Editor)
                : $access->role;
            foreach ($this->effectiveModuleRoles($moduleRole) as $effectiveRole) {
                $roles[] = sprintf('ROLE_MODULE_%s_%s', strtoupper($access->module->value), strtoupper($effectiveRole->value));
            }
        }

        return array_values(array_unique($roles));
    }

    public function getPassword(): string
    {
        return $this->identity->passwordHash;
    }

    public function eraseCredentials(): void
    {
    }

    /**
     * @return list<ModuleRole>
     */
    private function effectiveModuleRoles(ModuleRole $role): array
    {
        return match ($role) {
            ModuleRole::Viewer => [ModuleRole::Viewer],
            ModuleRole::Editor => [ModuleRole::Editor, ModuleRole::Viewer],
            ModuleRole::Publisher => [ModuleRole::Publisher, ModuleRole::Editor, ModuleRole::Viewer],
            ModuleRole::Moderator => [ModuleRole::Moderator, ModuleRole::Editor, ModuleRole::Viewer],
        };
    }
}
