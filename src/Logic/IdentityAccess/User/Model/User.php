<?php

namespace App\Logic\IdentityAccess\User\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

readonly class User
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
        public string $passwordHash,
        public array $roles,
        public array $moduleAccess,
        public bool $active,
        public int $version,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $lastLoginAt,
        public ?array $pageAccess = null,
    ) {
        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessRuleViolationException('Die E-Mail-Adresse ist ungültig.');
        }

        if (trim($this->displayName) === '') {
            throw new BusinessRuleViolationException('Der Anzeigename darf nicht leer sein.');
        }

        if ($this->moduleAccess === []) {
            throw new BusinessRuleViolationException('Ein Benutzer benötigt Zugriff auf mindestens ein Modul.');
        }

        if (count($this->roles) > 1) {
            throw new BusinessRuleViolationException('Ein Benutzer darf höchstens eine globale Administratorrolle besitzen.');
        }

        $moduleNames = array_map(static fn (ModuleAccess $access): string => $access->module->value, $this->moduleAccess);
        if (count($moduleNames) !== count(array_unique($moduleNames))) {
            throw new BusinessRuleViolationException('Ein Modul darf einem Benutzer nur einmal zugewiesen werden.');
        }

        if ($this->pageAccess === []) {
            throw new BusinessRuleViolationException('Ein eingeschränkter Seitenzugang benötigt mindestens eine Seitenberechtigung.');
        }

        $pageIds = array_map(static fn (PageAccess $access): string => $access->pageId, $this->pageAccess ?? []);
        if (count($pageIds) !== count(array_unique($pageIds))) {
            throw new BusinessRuleViolationException('Eine Seite darf einem Benutzer nur einmal zugewiesen werden.');
        }

        if ($this->pageAccess !== null && !in_array(CmsModule::Pages->value, $moduleNames, true)) {
            throw new BusinessRuleViolationException('Seitenberechtigungen setzen eine Freigabe des Moduls Seiten voraus.');
        }
    }

    /**
     * @param list<Role> $roles
     * @param list<ModuleAccess> $moduleAccess
     * @param list<PageAccess>|null $pageAccess
     */
    public function changeAccess(array $roles, array $moduleAccess, ?array $pageAccess, \DateTimeImmutable $updatedAt): self
    {
        return new self(
            id: $this->id,
            email: $this->email,
            displayName: $this->displayName,
            passwordHash: $this->passwordHash,
            roles: $roles,
            moduleAccess: $moduleAccess,
            active: $this->active,
            version: $this->version,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            lastLoginAt: $this->lastLoginAt,
            pageAccess: $pageAccess,
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
            moduleAccess: $this->moduleAccess,
            active: false,
            version: $this->version,
            createdAt: $this->createdAt,
            updatedAt: $updatedAt,
            lastLoginAt: $this->lastLoginAt,
            pageAccess: $this->pageAccess,
        );
    }
}
