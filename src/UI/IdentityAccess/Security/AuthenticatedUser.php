<?php

namespace App\UI\IdentityAccess\Security;

use App\Logic\IdentityAccess\Authentication\Dto\AuthenticationIdentity;
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

        return array_values(array_unique($roles));
    }

    public function getPassword(): string
    {
        return $this->identity->passwordHash;
    }

    public function eraseCredentials(): void
    {
    }
}
