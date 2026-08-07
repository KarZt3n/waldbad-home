<?php

namespace App\Logic\IdentityAccess\User\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class User
{
    /**
     * @param list<Role> $roles
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public string $passwordHash,
        public array $roles,
        public bool $active,
        public int $version,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $lastLoginAt,
    ) {
        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessRuleViolationException('Die E-Mail-Adresse ist ungültig.');
        }

        if (trim($this->displayName) === '') {
            throw new BusinessRuleViolationException('Der Anzeigename darf nicht leer sein.');
        }

        if ($this->roles === []) {
            throw new BusinessRuleViolationException('Ein Benutzer benötigt mindestens eine Rolle.');
        }
    }

    /**
     * @param list<Role> $roles
     */
    public function changeRoles(array $roles, \DateTimeImmutable $updatedAt): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            displayName: $this->displayName,
            passwordHash: $this->passwordHash,
            roles: $roles,
            active: $this->active,
            version: $this->version,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            lastLoginAt: $this->lastLoginAt,
        );
    }

    public function suspend(\DateTimeImmutable $updatedAt): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            displayName: $this->displayName,
            passwordHash: $this->passwordHash,
            roles: $this->roles,
            active: false,
            version: $this->version,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            lastLoginAt: $this->lastLoginAt,
        );
    }
}
