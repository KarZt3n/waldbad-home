<?php

namespace App\Data\IdentityAccess\User\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'cms_user')]
#[ORM\UniqueConstraint(name: 'uniq_cms_user_email', columns: ['email'])]
class UserEntity
{
    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $roles;

    /**
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $modules;

    /**
     * @var array<string, string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $pageAccess;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    /**
     * @param list<string> $roles
     * @param array<string, string> $modules
     * @param array<string, string>|null $pageAccess
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(type: Types::STRING, length: 36)]
        private string $id,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $email,
        #[ORM\Column(type: Types::STRING, length: 180)]
        private string $displayName,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $passwordHash,
        array $roles,
        array $modules,
        ?array $pageAccess,
        #[ORM\Column(type: Types::BOOLEAN)]
        private bool $active,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $lastLoginAt,
    ) {
        $this->roles = $roles;
        $this->modules = $modules;
        $this->pageAccess = $pageAccess;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * @return array<string, string>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * @return array<string, string>|null
     */
    public function getPageAccess(): ?array
    {
        return $this->pageAccess;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    /**
     * @param list<string> $roles
     * @param array<string, string> $modules
     * @param array<string, string>|null $pageAccess
     */
    public function update(
        string $displayName,
        string $passwordHash,
        array $roles,
        array $modules,
        ?array $pageAccess,
        bool $active,
        \DateTimeImmutable $updatedAt,
        ?\DateTimeImmutable $lastLoginAt,
    ): void {
        $this->displayName = $displayName;
        $this->passwordHash = $passwordHash;
        $this->roles = $roles;
        $this->modules = $modules;
        $this->pageAccess = $pageAccess;
        $this->active = $active;
        $this->updatedAt = $updatedAt;
        $this->lastLoginAt = $lastLoginAt;
    }
}
