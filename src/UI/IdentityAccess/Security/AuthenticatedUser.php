<?php

namespace App\UI\IdentityAccess\Security;

use App\Logic\IdentityAccess\Authentication\Dto\AuthenticationIdentity;
use App\Logic\IdentityAccess\Authorization\PageAuthorizationContext;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\Model\PageAccess;
use App\Logic\IdentityAccess\User\Model\PageAccessRole;
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
        if (!$this->isAdministrator()) {
            return $this->identity->moduleAccess;
        }

        // Admin und Super-Admin haben immer Zugriff auf alle Module, unabhängig davon, ob für sie
        // explizit ein Modulzugriff hinterlegt wurde (z. B. weil das Modul erst nachträglich hinzukam).
        return array_map(
            static fn (CmsModule $module): ModuleAccess => new ModuleAccess(
                $module,
                $module === CmsModule::Pages ? ModuleRole::Publisher : ModuleRole::Editor,
            ),
            CmsModule::cases(),
        );
    }

    /**
     * @return list<PageAccess>|null
     */
    public function getPageAccess(): ?array
    {
        return $this->identity->pageAccess;
    }

    public function pageAuthorizationContext(): PageAuthorizationContext
    {
        return new PageAuthorizationContext(
            roles: $this->identity->roles,
            moduleAccess: $this->identity->moduleAccess,
            pageAccess: $this->identity->pageAccess,
        );
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
        foreach ($this->getModuleAccess() as $access) {
            foreach ($this->effectiveModuleRoles($access->role) as $effectiveRole) {
                $roles[] = sprintf('ROLE_MODULE_%s_%s', strtoupper($access->module->value), strtoupper($effectiveRole->value));
            }
        }
        foreach ($this->identity->pageAccess ?? [] as $access) {
            $roles[] = Permission::PagesEdit->value;
            if ($access->role === PageAccessRole::Publisher) {
                $roles[] = Permission::PagesPublish->value;
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

    private function isAdministrator(): bool
    {
        return in_array(Role::Admin, $this->identity->roles, true)
            || in_array(Role::SuperAdmin, $this->identity->roles, true);
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
